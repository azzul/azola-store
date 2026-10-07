<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\Order;
use App\Models\Product;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Support\Qty;
use App\Support\Rupiah;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Retur penjualan, DP pelanggan, dan kelebihan transfer. Semua langsung berjurnal. */
class SalesDocumentService
{
    public function __construct(
        private StockService $stock,
        private JournalService $journals,
        private CostingService $costing,
    ) {}

    // ------------------------------------------------------------- Retur penjualan

    /**
     * @param  array<string, mixed>  $data  items: [{order_item_id, qty, restock?}], refund_method: cash|transfer|deposit
     */
    public function createReturn(Order $order, array $data, ?User $user = null): SaleReturn
    {
        return DB::transaction(function () use ($order, $data, $user) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['order' => 'Pesanan sudah dibatalkan.']);
            }

            $items = $locked->items()->get()->keyBy('id');
            $already = SaleReturnItem::whereIn('order_item_id', $items->keys())->selectRaw('order_item_id, SUM(qty) as q')->groupBy('order_item_id')->pluck('q', 'order_item_id');

            $lines = [];
            foreach ($data['items'] ?? [] as $row) {
                $milli = Qty::toMilli($row['qty'] ?? 0);
                if ($milli <= 0) {
                    continue;
                }

                $item = $items[(int) ($row['order_item_id'] ?? 0)] ?? null;
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Barang tidak ada di pesanan ini.']);
                }

                $left = Qty::toMilli($item->qty) - Qty::toMilli($already[$item->id] ?? 0);
                if ($milli > $left) {
                    throw ValidationException::withMessages(['items' => "Retur {$item->name} melebihi sisa yang bisa diretur (".Qty::pretty(Qty::fromMilli(max(0, $left))).').']);
                }

                $lines[] = [
                    'item' => $item, 'milli' => $milli,
                    'line' => (int) round($item->line_total * $milli / max(1, Qty::toMilli($item->qty))),
                    'restock' => (bool) ($row['restock'] ?? true) && $item->product_id,
                ];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['items' => 'Isi jumlah retur minimal satu barang.']);
            }

            // Potongan di tingkat pesanan dibagi rata, pajak mengikuti proporsi pesanan.
            $net = $locked->subtotal - $locked->discount_total;
            $itemsTotal = (int) $items->sum('line_total');
            $ratio = $itemsTotal > 0 ? $net / $itemsTotal : 1;
            $subtotal = (int) round(array_sum(array_column($lines, 'line')) * $ratio);
            $tax = $net > 0 ? (int) round($subtotal * $locked->tax_total / $net) : 0;
            $total = $subtotal + $tax;

            $offset = min($locked->outstanding(), $total);
            $payout = $total - $offset;
            $method = $data['refund_method'] ?? ($payout > 0 ? null : 'cash');

            if ($payout > 0 && ! in_array($method, ['cash', 'transfer', 'deposit'], true)) {
                throw ValidationException::withMessages(['refund_method' => 'Pilih cara mengembalikan uang: tunai, transfer, atau simpan sebagai DP.']);
            }

            $date = Carbon::parse($data['date'] ?? today());
            $return = SaleReturn::create([
                'order_id' => $locked->id, 'date' => $date, 'refund_method' => $payout > 0 ? $method : null,
                'subtotal' => $subtotal, 'tax' => $tax, 'total' => $total, 'offset_total' => $offset, 'paid_out' => $payout,
                'reason' => $data['reason'] ?? null, 'user_id' => $user?->id,
            ]);
            $return->forceFill(['number' => CostingService::number('RJ', $date, $return->id)])->save();

            $cogs = 0;
            foreach ($lines as $line) {
                /** @var \App\Models\OrderItem $item */
                $item = $line['item'];
                $return->items()->create([
                    'order_item_id' => $item->id, 'product_id' => $item->product_id, 'qty' => Qty::fromMilli($line['milli']),
                    'line_total' => $line['line'], 'unit_cost' => $item->unit_cost, 'restock' => $line['restock'],
                ]);

                if ($line['restock']) {
                    $value = Qty::value($line['milli'], (int) $item->unit_cost);
                    $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                    if ($product) {
                        $this->costing->revalue($product, $line['milli'], $value);
                        $this->stock->apply($product->id, $line['milli'], 'sale_return', $return, $locked->channel, $user?->id, 'Retur '.$return->number, (int) $item->unit_cost);
                        $cogs += $value;
                    }
                }
            }

            $payoutLines = [];
            if ($payout > 0) {
                if ($method === 'deposit') {
                    $this->newDeposit($locked, [
                        'kind' => 'return', 'method' => 'cash', 'amount' => $payout, 'date' => $date,
                        'note' => 'Dari retur '.$return->number,
                    ], $user);
                    $payoutLines[] = ['account' => 'customer_deposit', 'credit' => $payout];
                } else {
                    $payoutLines[] = ['account' => $method === 'cash' ? 'cash' : 'bank', 'credit' => $payout];
                    $locked->payments()->create(['kind' => 'refund', 'method' => $method, 'amount' => $payout, 'paid_at' => $date->copy()->setTimeFrom(now()), 'user_id' => $user?->id, 'note' => $return->number]);
                }
            }

            $this->journals->post('sale_return', 'Retur penjualan '.$return->number.' ('.$locked->number.')', array_merge([
                ['account' => 'sales_return', 'debit' => $subtotal],
                ['account' => 'tax_payable', 'debit' => $tax],
                ['account' => 'receivable', 'credit' => $offset],
                ['account' => 'inventory', 'debit' => $cogs],
                ['account' => 'cogs', 'credit' => $cogs],
            ], $payoutLines), $return, $date, $user?->id);

            $locked->forceFill(['returned_total' => $locked->returned_total + $offset])->save();
            $due = $locked->grand_total - $locked->returned_total;
            $locked->forceFill(['payment_status' => $locked->paid_total >= $due && $due >= 0 && $locked->paid_total > 0 ? 'paid' : ($locked->paid_total > 0 ? 'partial' : 'unpaid')])->save();

            return $return->load('items');
        });
    }

    // ------------------------------------------------------------------ DP customer

    /** @param  array<string, mixed>  $data */
    public function createDeposit(array $data, ?User $user = null): CustomerDeposit
    {
        return DB::transaction(function () use ($data, $user) {
            $order = ! empty($data['order_id']) ? Order::find($data['order_id']) : null;

            return $this->newDeposit($order, $data, $user, true);
        });
    }

    /** Pakai DP untuk melunasi sebagian/seluruh tagihan pesanan. */
    public function applyDeposit(CustomerDeposit $deposit, Order $order, int $amount, ?User $user = null): CustomerDeposit
    {
        return DB::transaction(function () use ($deposit, $order, $amount, $user) {
            $d = CustomerDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($o->isCancelled()) {
                throw ValidationException::withMessages(['order' => 'Pesanan sudah dibatalkan.']);
            }
            if ($amount <= 0 || $amount > $d->balance() || $amount > $o->outstanding()) {
                throw ValidationException::withMessages(['amount' => 'Jumlah harus lebih dari 0, tidak melebihi saldo DP ('.Rupiah::format($d->balance()).') maupun sisa tagihan ('.Rupiah::format($o->outstanding()).').']);
            }

            $this->journals->post('deposit_apply', 'Pakai DP '.$d->number.' untuk '.$o->number, [
                ['account' => 'customer_deposit', 'debit' => $amount],
                ['account' => 'receivable', 'credit' => $amount],
            ], $d, null, $user?->id);

            $o->payments()->create(['kind' => 'deposit', 'method' => 'deposit', 'amount' => $amount, 'paid_at' => now(), 'user_id' => $user?->id, 'note' => $d->number]);

            $paid = $o->paid_total + $amount;
            $due = $o->grand_total - $o->returned_total;
            $o->forceFill(['paid_total' => $paid, 'payment_status' => $paid >= $due && $due > 0 ? 'paid' : 'partial'])->save();

            if ($o->isWeb() && $o->fulfillment === 'new' && $o->payment_status === 'paid') {
                $o->forceFill(['fulfillment' => 'process'])->save();
            }

            $d->forceFill(['used_total' => $d->used_total + $amount])->save();

            return $d;
        });
    }

    public function refundDeposit(CustomerDeposit $deposit, int $amount, string $method, ?User $user = null): CustomerDeposit
    {
        if (! in_array($method, ['cash', 'transfer'], true)) {
            throw ValidationException::withMessages(['method' => 'Pengembalian lewat tunai atau transfer.']);
        }

        return DB::transaction(function () use ($deposit, $amount, $method, $user) {
            $d = CustomerDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($amount <= 0 || $amount > $d->balance()) {
                throw ValidationException::withMessages(['amount' => 'Jumlah harus lebih dari 0 dan tidak melebihi saldo DP ('.Rupiah::format($d->balance()).').']);
            }

            $this->journals->post('deposit_refund', 'Kembalikan DP '.$d->number, [
                ['account' => 'customer_deposit', 'debit' => $amount],
                ['account' => $method === 'cash' ? 'cash' : 'bank', 'credit' => $amount],
            ], $d, null, $user?->id);

            $d->forceFill(['refunded_total' => $d->refunded_total + $amount])->save();

            return $d;
        });
    }

    /**
     * Pelanggan transfer lebih dari tagihan. Kelebihan dicatat sebagai DP (kewajiban), lalu
     * dikembalikan lewat transfer saat itu juga atau disimpan untuk belanja berikutnya.
     */
    public function recordOverpayment(Order $order, int $amount, bool $refundNow, ?User $user = null): CustomerDeposit
    {
        return DB::transaction(function () use ($order, $amount, $refundNow, $user) {
            $deposit = $this->newDeposit($order, [
                'kind' => 'overpay', 'method' => 'transfer', 'amount' => $amount, 'date' => today(),
                'note' => 'Kelebihan transfer '.$order->number,
            ], $user, true);

            if ($refundNow) {
                $deposit = $this->refundDeposit($deposit, $amount, 'transfer', $user);
            }

            return $deposit;
        });
    }

    // ------------------------------------------------------------------ Internal

    /** @param  array<string, mixed>  $data */
    private function newDeposit(?Order $order, array $data, ?User $user, bool $journal = false): CustomerDeposit
    {
        $amount = (int) ($data['amount'] ?? 0);
        $method = $data['method'] ?? 'cash';

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Jumlah harus lebih dari 0.']);
        }
        if (! in_array($method, ['cash', 'transfer', 'qris', 'debit'], true)) {
            throw ValidationException::withMessages(['method' => 'Metode pembayaran tidak dikenal.']);
        }

        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : $order?->buyer;
        if (! $customer && ! $order && empty($data['customer_name'])) {
            throw ValidationException::withMessages(['customer_id' => 'Pilih customer atau isi nama.']);
        }

        $date = Carbon::parse($data['date'] ?? today());
        $deposit = CustomerDeposit::create([
            'customer_id' => $customer?->id, 'order_id' => $order?->id,
            'customer_name' => $customer?->name ?? $order?->customer_name ?? ($data['customer_name'] ?? null),
            'kind' => $data['kind'] ?? 'dp', 'method' => $method, 'date' => $date, 'amount' => $amount,
            'note' => $data['note'] ?? null, 'user_id' => $user?->id,
        ]);
        $deposit->forceFill(['number' => CostingService::number('DP', $date, $deposit->id)])->save();

        if ($journal) {
            $this->journals->post('deposit', 'Terima DP '.$deposit->number.($deposit->customer_name ? ' dari '.$deposit->customer_name : ''), [
                ['account' => in_array($method, ['cash'], true) ? 'cash' : 'bank', 'debit' => $amount],
                ['account' => 'customer_deposit', 'credit' => $amount],
            ], $deposit, $date, $user?->id);
        }

        return $deposit;
    }
}
