<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Qty;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Membuat, membayar, dan membatalkan pesanan untuk SEMUA kanal (web, kasir desktop, Android).
 * Harga selalu diambil dari database, bukan dari klien. Stok, pesanan, dan jurnal
 * ditulis dalam satu transaksi: semuanya berhasil atau tidak ada yang berubah.
 */
class OrderService
{
    public const METHODS = ['cash', 'transfer', 'qris', 'debit', 'cod'];

    public function __construct(
        private StockService $stock,
        private JournalService $journals,
    ) {}

    /**
     * Idempoten berdasarkan uuid dari klien: mengirim ulang uuid yang sama
     * (mis. POS mencoba lagi setelah koneksi putus) mengembalikan pesanan lama
     * dan TIDAK mengurangi stok dua kali.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Order, 1: bool} [pesanan, apakahBaruDibuat]
     */
    public function create(array $data, string $channel, ?User $user = null): array
    {
        $uuid = (string) ($data['uuid'] ?? Str::uuid());

        if ($existing = Order::with('items')->where('uuid', $uuid)->first()) {
            return [$existing, false];
        }

        try {
            $order = DB::transaction(fn () => $this->build($uuid, $data, $channel, $user));

            return [$order, true];
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan dengan uuid sama masuk bersamaan: yang kalah mengambil hasil pemenang.
            return [Order::with('items')->where('uuid', $uuid)->firstOrFail(), false];
        }
    }

    /** Terima pembayaran (pelunasan/cicilan) untuk pesanan yang masih punya sisa tagihan. */
    public function recordPayment(Order $order, int $amount, string $method, ?User $user = null): Order
    {
        if (! in_array($method, self::METHODS, true)) {
            throw ValidationException::withMessages(['method' => 'Metode pembayaran tidak dikenal.']);
        }

        return DB::transaction(function () use ($order, $amount, $method, $user) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['order' => 'Pesanan sudah dibatalkan.']);
            }
            if ($amount <= 0 || $amount > $locked->outstanding()) {
                throw ValidationException::withMessages(['amount' => 'Jumlah harus lebih dari 0 dan tidak melebihi sisa tagihan ('.$locked->outstanding().').']);
            }

            $account = $this->paymentAccount($method);

            $this->journals->post(
                'payment',
                'Pembayaran '.$locked->number.' via '.$method,
                [
                    ['account' => $account, 'debit' => $amount],
                    ['account' => 'receivable', 'credit' => $amount],
                ],
                $locked,
                null,
                $user?->id,
            );

            $paid = $locked->paid_total + $amount;
            $locked->forceFill([
                'paid_total' => $paid,
                'payment_status' => $paid >= $locked->grand_total ? 'paid' : 'partial',
            ])->save();

            return $locked->load('items');
        });
    }

    /** Batalkan pesanan: stok dikembalikan dan semua jurnalnya dibalik. Aman dipanggil berulang. */
    public function cancel(Order $order, ?User $user = null, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $user, $reason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                return $locked->load('items');
            }

            foreach ($locked->items()->orderBy('product_id')->get() as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $this->stock->apply(
                    $item->product_id,
                    Qty::toMilli($item->qty),
                    'sale_return',
                    $locked,
                    $locked->channel,
                    $user?->id,
                    'Batal '.$locked->number,
                    $item->unit_cost,
                );
            }

            $this->journals->reverseForSource($locked, $reason ?: 'pesanan dibatalkan', $user?->id);

            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'payment_status' => $locked->paid_total > 0 ? 'refunded' : 'unpaid',
            ])->save();

            return $locked->load('items');
        });
    }

    public function complete(Order $order): Order
    {
        if ($order->status === 'pending') {
            $order->forceFill(['status' => 'completed'])->save();
        }

        return $order;
    }

    // ------------------------------------------------------------------------

    private function build(string $uuid, array $data, string $channel, ?User $user): Order
    {
        $isWeb = $channel === 'web';
        $method = $data['payment_method'] ?? ($isWeb ? 'transfer' : 'cash');

        if (! in_array($method, self::METHODS, true)) {
            throw ValidationException::withMessages(['payment_method' => 'Metode pembayaran tidak dikenal.']);
        }

        $lines = $this->resolveLines($data['items'] ?? [], $isWeb);

        $subtotal = 0;
        $lineDiscounts = 0;
        $cogs = 0;
        foreach ($lines as $line) {
            $subtotal += $line['gross'];
            $lineDiscounts += $line['discount'];
            $cogs += $line['cogs'];
        }

        $orderDiscount = $isWeb ? 0 : max(0, (int) ($data['order_discount'] ?? 0));
        if ($lineDiscounts + $orderDiscount > $subtotal) {
            throw ValidationException::withMessages(['discount' => 'Diskon tidak boleh melebihi total belanja.']);
        }

        $discountTotal = $lineDiscounts + $orderDiscount;
        $net = $subtotal - $discountTotal;
        $tax = (int) round($net * (float) config('store.tax_rate') / 100);
        $shipping = max(0, (int) ($data['shipping_fee'] ?? 0));
        $grand = $net + $tax + $shipping;

        if ($isWeb) {
            $paid = 0;
        } else {
            $paid = array_key_exists('paid_total', $data) && $data['paid_total'] !== null
                ? (int) $data['paid_total']
                : $grand;
            $paid = max(0, min($paid, $grand));
        }

        $orderedAt = now();
        if (! $isWeb && ! empty($data['ordered_at'])) {
            // Transaksi offline dari POS membawa waktu aslinya, tapi tidak boleh di masa depan.
            $orderedAt = Carbon::parse($data['ordered_at'])->min(now());
        }

        $order = Order::create([
            'uuid' => $uuid,
            'channel' => $channel,
            'status' => $isWeb ? 'pending' : 'completed',
            'payment_method' => $method,
            'payment_status' => $paid >= $grand && $grand > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            'customer_name' => $data['customer_name'] ?? ($isWeb ? null : 'Pelanggan umum'),
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'delivery_method' => $data['delivery_method'] ?? 'pickup',
            'notes' => $data['notes'] ?? null,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $tax,
            'shipping_fee' => $shipping,
            'grand_total' => $grand,
            'paid_total' => $paid,
            'cogs_total' => $cogs,
            'user_id' => $user?->id,
            'ordered_at' => $orderedAt,
        ]);

        $order->forceFill(['number' => sprintf('INV-%s-%05d', $orderedAt->format('ymd'), $order->id)])->save();

        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];

            $order->items()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'qty' => Qty::fromMilli($line['milli']),
                'price' => $product->price,
                'discount' => $line['discount'],
                'line_total' => $line['gross'] - $line['discount'],
                'unit_cost' => (int) $product->cost,
            ]);

            // Dilakukan setelah semua validasi; bila stok kurang, seluruh transaksi di-rollback.
            $this->stock->apply(
                $product->id,
                -$line['milli'],
                'sale',
                $order,
                $channel,
                $user?->id,
                'Penjualan '.$order->number,
                (int) $product->cost,
            );
        }

        $this->journals->post(
            'sale',
            'Penjualan '.$order->number.' ('.$channel.')',
            [
                ['account' => $this->paymentAccount($method), 'debit' => $paid],
                ['account' => 'receivable', 'debit' => $grand - $paid],
                ['account' => 'sales', 'credit' => $net],
                ['account' => 'tax_payable', 'credit' => $tax],
                ['account' => 'shipping_income', 'credit' => $shipping],
                ['account' => 'cogs', 'debit' => $cogs],
                ['account' => 'inventory', 'credit' => $cogs],
            ],
            $order,
            $orderedAt,
            $user?->id,
        );

        return $order->load('items');
    }

    /**
     * Gabungkan baris duplikat, kunci semua produk berurutan id (mencegah deadlock),
     * lalu validasi dan hitung nilai tiap baris.
     *
     * @return array<int, array{product: Product, milli: int, discount: int, gross: int, cogs: int}>
     */
    private function resolveLines(array $items, bool $isWeb): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Keranjang kosong.']);
        }

        $skus = collect($items)->pluck('sku')->filter()->unique()->values()->all();
        $idsBySku = $skus ? Product::whereIn('sku', $skus)->pluck('id', 'sku') : collect();

        $wanted = [];
        foreach ($items as $item) {
            $id = $item['product_id'] ?? ($idsBySku[$item['sku'] ?? ''] ?? null);

            if (! $id) {
                throw ValidationException::withMessages(['items' => 'Produk tidak ditemukan: '.($item['sku'] ?? $item['product_id'] ?? '?')]);
            }

            $milli = Qty::toMilli($item['qty'] ?? 0);
            if ($milli <= 0) {
                throw ValidationException::withMessages(['items' => 'Jumlah barang harus lebih dari 0.']);
            }

            $wanted[$id]['milli'] = ($wanted[$id]['milli'] ?? 0) + $milli;
            $wanted[$id]['discount'] = ($wanted[$id]['discount'] ?? 0) + max(0, (int) ($item['discount'] ?? 0));
        }

        ksort($wanted);

        $products = Product::whereIn('id', array_keys($wanted))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $lines = [];

        foreach ($wanted as $id => $want) {
            $product = $products[$id] ?? null;

            if (! $product || ! $product->is_active || ($isWeb && ! $product->is_online)) {
                throw ValidationException::withMessages(['items' => 'Produk tidak tersedia untuk dijual: '.($product?->name ?? "#{$id}")]);
            }

            $gross = Qty::value($want['milli'], $product->price);
            $discount = $isWeb ? 0 : $want['discount'];

            if ($discount > $gross) {
                throw ValidationException::withMessages(['items' => 'Diskon melebihi harga untuk '.$product->name.'.']);
            }

            $lines[] = [
                'product' => $product,
                'milli' => $want['milli'],
                'discount' => $discount,
                'gross' => $gross,
                'cogs' => Qty::value($want['milli'], (int) $product->cost),
            ];
        }

        return $lines;
    }

    private function paymentAccount(string $method): string
    {
        return in_array($method, ['cash', 'cod'], true) ? 'cash' : 'bank';
    }
}
