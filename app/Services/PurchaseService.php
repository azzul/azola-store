<?php

namespace App\Services;

use App\Models\PayableAllocation;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Qty;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pembelian, retur pembelian, dan pembayaran hutang. Setiap dokumen mengubah stok, HPP, hutang,
 * dan jurnal dalam satu transaksi database.
 */
class PurchaseService
{
    public function __construct(
        private StockService $stock,
        private JournalService $journals,
        private CostingService $costing,
        private LineResolver $resolver,
    ) {}

    // ---------------------------------------------------------------- Pembelian

    /** @param  array<string, mixed>  $data */
    public function create(array $data, ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($data, $user) {
            $head = $this->head($data);
            $lines = $this->prepareLines($data);
            $grand = array_sum(array_column($lines, 'net_total'));
            [$initialPaid, $paidVia] = $this->initialPayment($head['payment_method'], $grand, $data);

            $purchase = Purchase::create($head + [
                'subtotal' => array_sum(array_column($lines, 'line_total')),
                'discount' => (int) ($data['discount'] ?? 0),
                'grand_total' => $grand,
                'paid_total' => $initialPaid,
                'user_id' => $user?->id,
            ]);
            $purchase->forceFill(['number' => CostingService::number('PB', $purchase->date, $purchase->id)])->save();

            $this->writeItems($purchase, $lines);
            $this->applyStock($purchase, $this->bucket($lines, $head['warehouse_id']), [], $user, 'purchase');
            $this->postJournal($purchase, $initialPaid, $paidVia, $user);

            return $purchase->load('items.product', 'supplier');
        });
    }

    /**
     * Ubah faktur. Selisih stok dan nilai dihitung per produk (bukan membongkar lalu memasang ulang),
     * jurnal lama dibalik pada tanggal aslinya, lalu jurnal baru diposting.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Purchase $purchase, array $data, ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $user) {
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['purchase' => 'Faktur yang sudah dibatalkan tidak bisa diubah.']);
            }

            $locked->load('items');
            $allocated = (int) $locked->allocations()->sum('amount');

            $head = $this->head($data);
            $lines = $this->prepareLines($data);
            $grand = array_sum(array_column($lines, 'net_total'));
            [$initialPaid, $paidVia] = $this->initialPayment($head['payment_method'], $grand, $data);

            if ($head['payment_method'] !== 'credit' && $allocated > 0) {
                throw ValidationException::withMessages(['payment_method' => 'Faktur ini sudah punya pelunasan/potongan retur, jadi tetap harus berstatus kredit.']);
            }
            if ($initialPaid + $allocated > $grand) {
                throw ValidationException::withMessages(['items' => 'Total faktur tidak boleh lebih kecil dari yang sudah dibayar ('.($initialPaid + $allocated).').']);
            }

            $old = [];
            foreach ($locked->items as $item) {
                $key = $item->product_id.'|'.($locked->warehouse_id ?? Warehouse::mainId());
                $old[$key]['milli'] = ($old[$key]['milli'] ?? 0) + Qty::toMilli($item->base_qty);
                $old[$key]['value'] = ($old[$key]['value'] ?? 0) + $item->net_total;
            }

            $this->applyStock($locked, $this->bucket($lines, $head['warehouse_id']), $old, $user, 'purchase_edit');

            // Jurnal lama dibalik di tanggalnya sendiri agar laporan periode lampau tidak berubah ganda.
            $this->journals->reverseForSource($locked, 'koreksi faktur', $user?->id, true);

            $locked->items()->delete();
            $locked->forceFill($head + [
                'subtotal' => array_sum(array_column($lines, 'line_total')),
                'discount' => (int) ($data['discount'] ?? 0),
                'grand_total' => $grand,
                'paid_total' => $initialPaid + $allocated,
            ])->save();

            $this->writeItems($locked, $lines);
            $this->postJournal($locked, $initialPaid + $allocated, $paidVia, $user, $allocated);

            return $locked->load('items.product', 'supplier');
        });
    }

    public function cancel(Purchase $purchase, ?User $user = null, ?string $reason = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $user, $reason) {
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                return $locked;
            }
            if ($locked->allocations()->exists()) {
                throw ValidationException::withMessages(['purchase' => 'Faktur ini sudah punya pembayaran hutang atau potongan retur. Batalkan itu dulu.']);
            }
            if (PurchaseReturn::where('purchase_id', $locked->id)->where('status', 'posted')->exists()) {
                throw ValidationException::withMessages(['purchase' => 'Faktur ini punya retur pembelian aktif. Batalkan retur dulu.']);
            }

            $old = [];
            foreach ($locked->items()->get() as $item) {
                $key = $item->product_id.'|'.($locked->warehouse_id ?? Warehouse::mainId());
                $old[$key]['milli'] = ($old[$key]['milli'] ?? 0) + Qty::toMilli($item->base_qty);
                $old[$key]['value'] = ($old[$key]['value'] ?? 0) + $item->net_total;
            }

            $this->applyStock($locked, [], $old, $user, 'purchase_void');
            $this->journals->reverseForSource($locked, $reason ?: 'faktur dibatalkan', $user?->id);

            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'paid_total' => 0])->save();

            return $locked;
        });
    }

    // ------------------------------------------------------------ Retur pembelian

    /** @param  array<string, mixed>  $data */
    public function createReturn(array $data, ?User $user = null): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $user) {
            $supplier = Supplier::find($data['supplier_id'] ?? null);
            $purchase = ! empty($data['purchase_id']) ? Purchase::find($data['purchase_id']) : null;
            $supplier ??= $purchase?->supplier;

            if (! $supplier) {
                throw ValidationException::withMessages(['supplier_id' => 'Pilih supplier.']);
            }

            $settlement = $data['settlement'] ?? 'payable';
            if (! in_array($settlement, ['payable', 'cash', 'bank', 'receivable'], true)) {
                throw ValidationException::withMessages(['settlement' => 'Cara penyelesaian tidak dikenal.']);
            }

            $date = Carbon::parse($data['date'] ?? today());
            $warehouseId = (int) ($data['warehouse_id'] ?? 0) ?: Warehouse::mainId();
            $lines = $this->resolver->resolve($data['items'] ?? []);
            $total = array_sum(array_column($lines, 'line_total'));

            if ($purchase) {
                $this->assertReturnable($purchase, $lines);
            }

            $return = PurchaseReturn::create([
                'supplier_id' => $supplier->id, 'purchase_id' => $purchase?->id, 'warehouse_id' => $warehouseId,
                'date' => $date, 'settlement' => $settlement, 'total' => $total,
                'reason' => $data['reason'] ?? null, 'user_id' => $user?->id,
            ]);
            $return->forceFill(['number' => CostingService::number('RB', $date, $return->id)])->save();

            $residual = 0;
            foreach ($this->mergeLines($lines) as $pid => $agg) {
                $product = Product::whereKey($pid)->lockForUpdate()->firstOrFail();
                $residual += $this->costing->revalue($product, -$agg['milli'], -$agg['value']);
                $this->stock->apply($pid, -$agg['milli'], 'purchase_return', $return, 'admin', $user?->id, 'Retur '.$return->number, null, false, $warehouseId);
            }

            foreach ($lines as $line) {
                $return->items()->create([
                    'product_id' => $line['product']->id, 'unit' => $line['unit'], 'factor' => $line['factor'],
                    'qty' => Qty::fromMilli($line['qty_milli']), 'base_qty' => Qty::fromMilli($line['base_milli']),
                    'price' => $line['price'], 'line_total' => $line['line_total'],
                ]);
            }

            $offset = 0;
            if ($settlement === 'payable') {
                $offset = $this->allocate($supplier->id, $total, $return, $purchase?->id);
            }

            $journalLines = [['account' => 'inventory', 'credit' => $total]];
            $journalLines[] = match ($settlement) {
                'cash' => ['account' => 'cash', 'debit' => $total],
                'bank' => ['account' => 'bank', 'debit' => $total],
                'receivable' => ['account' => 'supplier_receivable', 'debit' => $total],
                default => ['account' => 'payable', 'debit' => $offset],
            };
            if ($settlement === 'payable' && $total > $offset) {
                $journalLines[] = ['account' => 'supplier_receivable', 'debit' => $total - $offset, 'memo' => 'Melebihi hutang: jadi piutang supplier'];
            }
            $journalLines = array_merge($journalLines, $this->costing->residualLines($residual));

            $this->journals->post('purchase_return', 'Retur pembelian '.$return->number.' ke '.$supplier->name, $journalLines, $return, $date, $user?->id);

            if ($purchase) {
                $purchase->increment('returned_total', $total);
            }

            return $return->load('items.product', 'supplier');
        });
    }

    public function cancelReturn(PurchaseReturn $return, ?User $user = null): PurchaseReturn
    {
        return DB::transaction(function () use ($return, $user) {
            $locked = PurchaseReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'cancelled') {
                return $locked;
            }

            if ($locked->settlement === 'receivable') {
                $supplier = $locked->supplier;
                if ($supplier && $supplier->receivable() - $locked->total < 0
                    && SupplierPayment::where('supplier_id', $supplier->id)->where('direction', 'receive')->where('status', 'posted')->exists()) {
                    throw ValidationException::withMessages(['return' => 'Piutang dari retur ini sudah ditagih sebagian. Batalkan penerimaannya dulu.']);
                }
            }

            foreach ($this->mergeLines($this->returnLines($locked)) as $pid => $agg) {
                $product = Product::whereKey($pid)->lockForUpdate()->firstOrFail();
                $this->costing->revalue($product, $agg['milli'], $agg['value']);
                $this->stock->apply($pid, $agg['milli'], 'purchase_return_void', $locked, 'admin', $user?->id, 'Batal '.$locked->number, null, false, $locked->warehouse_id);
            }

            $this->journals->reverseForSource($locked, 'retur dibatalkan', $user?->id);
            $this->releaseAllocations($locked);

            if ($locked->purchase_id) {
                Purchase::whereKey($locked->purchase_id)->decrement('returned_total', $locked->total);
            }

            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

            return $locked;
        });
    }

    // ------------------------------------------------------------- Pembayaran hutang

    /**
     * @param  array<int, int>|null  $purchaseIds  faktur yang dilunasi (berurutan); kosong = otomatis dari yang paling lama
     */
    public function pay(Supplier $supplier, int $amount, string $method, ?string $date = null, ?array $purchaseIds = null, ?string $note = null, ?User $user = null): SupplierPayment
    {
        if (! in_array($method, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages(['method' => 'Pembayaran lewat Kas atau Bank.']);
        }

        return DB::transaction(function () use ($supplier, $amount, $method, $date, $purchaseIds, $note, $user) {
            $payable = $supplier->payable();

            if ($amount <= 0 || $amount > $payable) {
                throw ValidationException::withMessages(['amount' => 'Jumlah harus lebih dari 0 dan tidak melebihi hutang ('.\App\Support\Rupiah::format($payable).').']);
            }

            $when = Carbon::parse($date ?? today());
            $payment = SupplierPayment::create([
                'supplier_id' => $supplier->id, 'direction' => 'pay', 'method' => $method, 'date' => $when,
                'amount' => $amount, 'note' => $note, 'user_id' => $user?->id,
            ]);
            $payment->forceFill(['number' => CostingService::number('BH', $when, $payment->id)])->save();

            $applied = $this->allocate($supplier->id, $amount, $payment, null, $purchaseIds);
            if ($applied !== $amount) {
                throw ValidationException::withMessages(['amount' => 'Jumlah melebihi sisa faktur yang dipilih.']);
            }

            $this->journals->post('supplier_payment', 'Pembayaran hutang '.$payment->number.' ke '.$supplier->name, [
                ['account' => 'payable', 'debit' => $amount],
                ['account' => $method, 'credit' => $amount],
            ], $payment, $when, $user?->id);

            return $payment;
        });
    }

    /** Terima uang dari supplier atas piutang (mis. pengembalian dana retur). */
    public function receive(Supplier $supplier, int $amount, string $method, ?string $date = null, ?string $note = null, ?User $user = null): SupplierPayment
    {
        if (! in_array($method, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages(['method' => 'Penerimaan lewat Kas atau Bank.']);
        }

        return DB::transaction(function () use ($supplier, $amount, $method, $date, $note, $user) {
            $open = $supplier->receivable();

            if ($amount <= 0 || $amount > $open) {
                throw ValidationException::withMessages(['amount' => 'Jumlah harus lebih dari 0 dan tidak melebihi piutang supplier ('.\App\Support\Rupiah::format($open).').']);
            }

            $when = Carbon::parse($date ?? today());
            $payment = SupplierPayment::create([
                'supplier_id' => $supplier->id, 'direction' => 'receive', 'method' => $method, 'date' => $when,
                'amount' => $amount, 'note' => $note, 'user_id' => $user?->id,
            ]);
            $payment->forceFill(['number' => CostingService::number('TP', $when, $payment->id)])->save();

            $this->journals->post('supplier_payment', 'Penerimaan piutang supplier '.$payment->number.' dari '.$supplier->name, [
                ['account' => $method, 'debit' => $amount],
                ['account' => 'supplier_receivable', 'credit' => $amount],
            ], $payment, $when, $user?->id);

            return $payment;
        });
    }

    public function cancelPayment(SupplierPayment $payment, ?User $user = null): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $user) {
            $locked = SupplierPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'cancelled') {
                return $locked;
            }

            $this->journals->reverseForSource($locked, 'pembayaran dibatalkan', $user?->id);
            $this->releaseAllocations($locked);
            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

            return $locked;
        });
    }

    // ------------------------------------------------------------------ Internal

    /** @param  array<string, mixed>  $data */
    private function head(array $data): array
    {
        $method = $data['payment_method'] ?? 'cash';
        if (! in_array($method, ['cash', 'bank', 'credit'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Cara bayar tidak dikenal.']);
        }

        $supplier = ! empty($data['supplier_id']) ? Supplier::find($data['supplier_id']) : null;
        if ($method === 'credit' && ! $supplier) {
            throw ValidationException::withMessages(['supplier_id' => 'Pembelian kredit wajib memilih supplier.']);
        }

        $date = Carbon::parse($data['date'] ?? today());
        $due = null;
        if ($method === 'credit') {
            $due = ! empty($data['due_date']) ? Carbon::parse($data['due_date']) : $date->copy()->addDays((int) $supplier->term_days);
        }

        return [
            'supplier_id' => $supplier?->id,
            'warehouse_id' => (int) ($data['warehouse_id'] ?? 0) ?: Warehouse::mainId(),
            'supplier_invoice' => $data['supplier_invoice'] ?? null,
            'date' => $date, 'due_date' => $due,
            'payment_method' => $method,
            'note' => $data['note'] ?? null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function prepareLines(array $data): array
    {
        return $this->resolver->allocateDiscount(
            $this->resolver->resolve($data['items'] ?? []),
            max(0, (int) ($data['discount'] ?? 0)),
        );
    }

    /** @return array{0: int, 1: string} [dibayar saat input, akun pembayaran] */
    private function initialPayment(string $method, int $grand, array $data): array
    {
        if ($method !== 'credit') {
            return [$grand, $method];
        }

        $via = ($data['down_payment_via'] ?? 'cash') === 'bank' ? 'bank' : 'cash';
        $dp = max(0, (int) ($data['down_payment'] ?? 0));

        if ($dp > $grand) {
            throw ValidationException::withMessages(['down_payment' => 'Uang muka tidak boleh melebihi total faktur.']);
        }

        return [$dp, $via];
    }

    private function writeItems(Purchase $purchase, array $lines): void
    {
        foreach ($lines as $line) {
            $purchase->items()->create([
                'product_id' => $line['product']->id, 'unit' => $line['unit'], 'factor' => $line['factor'],
                'qty' => Qty::fromMilli($line['qty_milli']), 'base_qty' => Qty::fromMilli($line['base_milli']),
                'price' => $line['price'], 'line_total' => $line['line_total'], 'net_total' => $line['net_total'],
            ]);
        }
    }

    /** Gabungkan baris per produk|gudang: jumlah dasar dan nilai bersih. */
    private function bucket(array $lines, int $warehouseId): array
    {
        $out = [];
        foreach ($lines as $line) {
            $key = $line['product']->id.'|'.$warehouseId;
            $out[$key]['milli'] = ($out[$key]['milli'] ?? 0) + $line['base_milli'];
            $out[$key]['value'] = ($out[$key]['value'] ?? 0) + $line['net_total'];
        }

        return $out;
    }

    /**
     * Terapkan selisih stok + HPP antara kondisi lama ($old) dan baru ($new), per produk|gudang.
     *
     * @param  array<string, array{milli: int, value: int}>  $new
     * @param  array<string, array{milli: int, value: int}>  $old
     */
    private function applyStock(Purchase $purchase, array $new, array $old, ?User $user, string $type): void
    {
        $keys = array_unique(array_merge(array_keys($new), array_keys($old)));
        sort($keys);

        $residual = 0;
        foreach ($keys as $key) {
            [$pid, $wid] = array_map('intval', explode('|', $key));
            $dq = ($new[$key]['milli'] ?? 0) - ($old[$key]['milli'] ?? 0);
            $dv = ($new[$key]['value'] ?? 0) - ($old[$key]['value'] ?? 0);

            if ($dq === 0 && $dv === 0) {
                continue;
            }

            $product = Product::whereKey($pid)->lockForUpdate()->firstOrFail();
            $residual += $this->costing->revalue($product, $dq, $dv);

            if ($dq !== 0) {
                $unit = ($new[$key]['milli'] ?? 0) > 0
                    ? (int) round($new[$key]['value'] * 1000 / $new[$key]['milli'])
                    : (int) $product->cost;
                $this->stock->apply($pid, $dq, $type, $purchase, 'admin', $user?->id, ucfirst(str_replace('_', ' ', $type)).' '.$purchase->number, $unit, false, $wid);
            }
        }

        if ($residual > 0) {
            $this->journals->post('adjustment', 'Sisa nilai persediaan dihapus ('.$purchase->number.')', $this->costing->residualLines($residual), $purchase, null, $user?->id);
        }
    }

    private function postJournal(Purchase $purchase, int $paid, string $paidVia, ?User $user, int $allocatedPart = 0): void
    {
        // $paid sudah termasuk pelunasan/potongan retur (allocatedPart) yang dibayarkan lewat jurnal terpisah;
        // jurnal faktur hanya mencatat bagian awal.
        $initial = $paid - $allocatedPart;
        $grand = $purchase->grand_total;

        $this->journals->post('purchase', 'Pembelian '.$purchase->number.($purchase->supplier ? ' dari '.$purchase->supplier->name : ''), [
            ['account' => 'inventory', 'debit' => $grand],
            ['account' => $paidVia, 'credit' => $initial],
            ['account' => 'payable', 'credit' => $grand - $initial],
        ], $purchase, $purchase->date, $user?->id);
    }

    /**
     * Alokasikan $amount ke faktur yang masih punya sisa: FIFO (jatuh tempo lalu tanggal) atau sesuai daftar.
     * Mengembalikan jumlah yang benar-benar teralokasi.
     */
    private function allocate(int $supplierId, int $amount, $source, ?int $preferPurchaseId = null, ?array $purchaseIds = null): int
    {
        $query = Purchase::where('supplier_id', $supplierId)->where('status', 'posted')->whereColumn('paid_total', '<', 'grand_total')->lockForUpdate();

        if ($purchaseIds) {
            $purchases = $query->whereIn('id', $purchaseIds)->get()->sortBy(fn ($p) => array_search($p->id, $purchaseIds))->values();
        } else {
            $purchases = $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('date')->orderBy('id')->get();
            if ($preferPurchaseId) {
                $purchases = $purchases->sortBy(fn ($p) => $p->id === $preferPurchaseId ? 0 : 1)->values();
            }
        }

        $left = $amount;
        foreach ($purchases as $purchase) {
            if ($left <= 0) {
                break;
            }

            $take = min($left, $purchase->grand_total - $purchase->paid_total);
            PayableAllocation::create(['purchase_id' => $purchase->id, 'source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'amount' => $take]);
            $purchase->increment('paid_total', $take);
            $left -= $take;
        }

        return $amount - $left;
    }

    private function releaseAllocations($source): void
    {
        foreach (PayableAllocation::where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->get() as $allocation) {
            Purchase::whereKey($allocation->purchase_id)->decrement('paid_total', $allocation->amount);
            $allocation->delete();
        }
    }

    /** Retur tidak boleh melebihi jumlah yang dibeli dikurangi retur sebelumnya (per produk, satuan dasar). */
    private function assertReturnable(Purchase $purchase, array $lines): void
    {
        $bought = [];
        foreach ($purchase->items as $item) {
            $bought[$item->product_id] = ($bought[$item->product_id] ?? 0) + Qty::toMilli($item->base_qty);
        }

        $returned = [];
        foreach (PurchaseReturn::where('purchase_id', $purchase->id)->where('status', 'posted')->with('items')->get() as $r) {
            foreach ($r->items as $item) {
                $returned[$item->product_id] = ($returned[$item->product_id] ?? 0) + Qty::toMilli($item->base_qty);
            }
        }

        foreach ($this->mergeLines($lines) as $pid => $agg) {
            $max = ($bought[$pid] ?? 0) - ($returned[$pid] ?? 0);
            if ($agg['milli'] > $max) {
                $name = Product::find($pid)?->name;
                throw ValidationException::withMessages(['items' => "Retur {$name} melebihi jumlah pembelian (sisa bisa diretur: ".Qty::pretty(Qty::fromMilli(max(0, $max))).').']);
            }
        }
    }

    /** @return array<int, array{milli: int, value: int}> per produk */
    private function mergeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $pid = $line['product']->id;
            $out[$pid]['milli'] = ($out[$pid]['milli'] ?? 0) + $line['base_milli'];
            $out[$pid]['value'] = ($out[$pid]['value'] ?? 0) + $line['line_total'];
        }
        ksort($out);

        return $out;
    }

    private function returnLines(PurchaseReturn $return): array
    {
        return $return->items()->with('product')->get()->map(fn ($i) => [
            'product' => $i->product, 'base_milli' => Qty::toMilli($i->base_qty), 'line_total' => $i->line_total,
        ])->all();
    }
}
