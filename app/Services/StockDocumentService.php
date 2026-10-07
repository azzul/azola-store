<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Qty;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Alih gudang (kirim + penerimaan), penyesuaian stok, dan stok opname. */
class StockDocumentService
{
    public function __construct(
        private StockService $stock,
        private JournalService $journals,
        private LineResolver $resolver,
    ) {}

    // ---------------------------------------------------------------- Alih gudang

    /** @param  array<string, mixed>  $data */
    public function createTransfer(array $data, ?User $user = null): StockTransfer
    {
        $from = (int) ($data['from_warehouse_id'] ?? 0);
        $to = (int) ($data['to_warehouse_id'] ?? 0);

        if (! Warehouse::whereKey($from)->exists() || ! Warehouse::whereKey($to)->exists() || $from === $to) {
            throw ValidationException::withMessages(['to_warehouse_id' => 'Pilih gudang asal dan tujuan yang berbeda.']);
        }

        $lines = $this->resolver->resolve($data['items'] ?? []);

        return DB::transaction(function () use ($data, $user, $from, $to, $lines) {
            $date = Carbon::parse($data['date'] ?? today());
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $from, 'to_warehouse_id' => $to, 'date' => $date,
                'note' => $data['note'] ?? null, 'user_id' => $user?->id,
            ]);
            $transfer->forceFill(['number' => CostingService::number('AG', $date, $transfer->id)])->save();

            $merged = [];
            foreach ($lines as $line) {
                $merged[$line['product']->id] = ($merged[$line['product']->id] ?? 0) + $line['base_milli'];
            }
            ksort($merged);

            foreach ($merged as $pid => $milli) {
                $product = Product::findOrFail($pid);
                $transfer->items()->create(['product_id' => $pid, 'qty' => Qty::fromMilli($milli), 'unit_cost' => (int) $product->cost]);
                $this->stock->apply($pid, -$milli, 'transfer_out', $transfer, 'admin', $user?->id, 'Alih gudang '.$transfer->number, (int) $product->cost, false, $from);
            }

            return $transfer->load('items.product', 'from', 'to');
        });
    }

    /**
     * Terima kiriman. $received: [product_id => jumlah diterima]; kosong = diterima penuh.
     * Kekurangan dibukukan sebagai kerusakan/penyusutan stok (nilai persediaan berkurang).
     *
     * @param  array<int|string, string|int|float|null>  $received
     */
    public function receiveTransfer(StockTransfer $transfer, array $received = [], ?string $date = null, ?User $user = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $received, $date, $user) {
            $locked = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages(['transfer' => 'Kiriman ini sudah diterima atau dibatalkan.']);
            }

            $when = Carbon::parse($date ?? today());
            $lossValue = 0;

            foreach ($locked->items()->orderBy('product_id')->get() as $item) {
                $sent = Qty::toMilli($item->qty);
                $got = array_key_exists($item->product_id, $received) && $received[$item->product_id] !== null && $received[$item->product_id] !== ''
                    ? Qty::toMilli($received[$item->product_id]) : $sent;

                if ($got < 0 || $got > $sent) {
                    throw ValidationException::withMessages(['received' => 'Jumlah diterima harus antara 0 dan jumlah dikirim.']);
                }

                if ($got > 0) {
                    $this->stock->apply($item->product_id, $got, 'transfer_in', $locked, 'admin', $user?->id, 'Penerimaan '.$locked->number, (int) $item->unit_cost, false, $locked->to_warehouse_id);
                }

                $lossValue += Qty::value($sent - $got, (int) $item->unit_cost);
                $item->forceFill(['received_qty' => Qty::fromMilli($got)])->save();
            }

            if ($lossValue > 0) {
                $this->journals->post('adjustment', 'Selisih penerimaan alih gudang '.$locked->number, [
                    ['account' => 'inventory_shrinkage', 'debit' => $lossValue],
                    ['account' => 'inventory', 'credit' => $lossValue],
                ], $locked, $when, $user?->id);
            }

            $locked->forceFill(['status' => 'received', 'received_on' => $when, 'received_by' => $user?->id])->save();

            return $locked->load('items.product');
        });
    }

    public function cancelTransfer(StockTransfer $transfer, ?User $user = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $locked = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages(['transfer' => 'Hanya kiriman yang belum diterima yang bisa dibatalkan.']);
            }

            foreach ($locked->items()->orderBy('product_id')->get() as $item) {
                $this->stock->apply($item->product_id, Qty::toMilli($item->qty), 'transfer_cancel', $locked, 'admin', $user?->id, 'Batal '.$locked->number, (int) $item->unit_cost, false, $locked->from_warehouse_id);
            }

            $locked->forceFill(['status' => 'cancelled'])->save();

            return $locked;
        });
    }

    // ------------------------------------------------------------ Penyesuaian stok

    /** @param  array<string, mixed>  $data  items: [{product_id, qty_change (+/-)}] */
    public function createAdjustment(array $data, ?User $user = null): StockAdjustment
    {
        $reason = $data['reason'] ?? 'other';
        if (! isset(StockAdjustment::REASONS[$reason])) {
            throw ValidationException::withMessages(['reason' => 'Alasan tidak dikenal.']);
        }

        $items = [];
        foreach ($data['items'] ?? [] as $row) {
            $milli = Qty::toMilli($row['qty_change'] ?? 0);
            if (empty($row['product_id']) || $milli === 0) {
                continue;
            }
            $items[(int) $row['product_id']] = ($items[(int) $row['product_id']] ?? 0) + $milli;
        }
        $items = array_filter($items);

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Isi minimal satu barang dengan selisih jumlah (boleh minus).']);
        }
        ksort($items);

        return DB::transaction(function () use ($data, $user, $reason, $items) {
            $date = Carbon::parse($data['date'] ?? today());
            $warehouseId = (int) ($data['warehouse_id'] ?? 0) ?: Warehouse::mainId();
            $adjustment = StockAdjustment::create([
                'warehouse_id' => $warehouseId, 'date' => $date, 'reason' => $reason,
                'note' => $data['note'] ?? null, 'user_id' => $user?->id,
            ]);
            $adjustment->forceFill(['number' => CostingService::number('PS', $date, $adjustment->id)])->save();

            $account = in_array($reason, ['damaged', 'lost', 'expired', 'shrinkage'], true) ? 'inventory_shrinkage' : 'inventory_adjustment';
            $gain = 0;
            $loss = 0;
            $net = 0;

            foreach ($items as $pid => $milli) {
                $product = Product::whereKey($pid)->firstOrFail();
                $adjustment->items()->create(['product_id' => $pid, 'qty_change' => Qty::fromMilli($milli), 'unit_cost' => (int) $product->cost]);
                $this->stock->apply($pid, $milli, 'adjustment', $adjustment, 'admin', $user?->id, StockAdjustment::REASONS[$reason].' '.$adjustment->number, (int) $product->cost, false, $warehouseId);

                $value = Qty::value(abs($milli), (int) $product->cost);
                if ($milli > 0) {
                    $gain += $value;
                    $net += $value;
                } else {
                    $loss += $value;
                    $net -= $value;
                }
            }

            $this->journals->post('adjustment', 'Penyesuaian stok '.$adjustment->number.' ('.StockAdjustment::REASONS[$reason].')', [
                ['account' => 'inventory', 'debit' => $gain],
                ['account' => $account, 'credit' => $gain],
                ['account' => $account, 'debit' => $loss],
                ['account' => 'inventory', 'credit' => $loss],
            ], $adjustment, $date, $user?->id);

            $adjustment->forceFill(['value_total' => $net])->save();

            return $adjustment->load('items.product');
        });
    }

    // ---------------------------------------------------------------- Stok opname

    /** Mulai sesi hitung: daftar produk aktif dengan stok sistem saat ini sebagai acuan. */
    public function startOpname(array $data, ?User $user = null): StockOpname
    {
        return DB::transaction(function () use ($data, $user) {
            $date = Carbon::parse($data['date'] ?? today());
            $warehouseId = (int) ($data['warehouse_id'] ?? 0) ?: Warehouse::mainId();
            $isMain = $warehouseId === Warehouse::mainId();

            $opname = StockOpname::create([
                'warehouse_id' => $warehouseId, 'date' => $date, 'note' => $data['note'] ?? null, 'user_id' => $user?->id,
            ]);
            $opname->forceFill(['number' => CostingService::number('SO', $date, $opname->id)])->save();

            $products = Product::where('is_active', true)
                ->when(! empty($data['category_id']), fn ($q) => $q->where('category_id', $data['category_id']))
                ->orderBy('name')->get();
            $balances = $isMain ? collect() : DB::table('stock_balances')->where('warehouse_id', $warehouseId)->pluck('qty', 'product_id');

            foreach ($products as $product) {
                $opname->items()->create([
                    'product_id' => $product->id,
                    'system_qty' => $isMain ? $product->stock_qty : ($balances[$product->id] ?? 0),
                    'unit_cost' => (int) $product->cost,
                ]);
            }

            return $opname;
        });
    }

    /** @param  array<int|string, string|int|float|null>  $counts  [item_id => hasil hitung]; kosong = belum dihitung */
    public function saveCounts(StockOpname $opname, array $counts, array $notes = []): StockOpname
    {
        if ($opname->isFinal()) {
            throw ValidationException::withMessages(['opname' => 'Opname sudah final dan tidak bisa diubah.']);
        }

        foreach ($opname->items as $item) {
            if (! array_key_exists($item->id, $counts)) {
                continue;
            }
            $raw = $counts[$item->id];
            $counted = ($raw === null || $raw === '') ? null : Qty::toMilli($raw);
            if ($counted !== null && $counted < 0) {
                throw ValidationException::withMessages(['counts' => 'Hasil hitung tidak boleh negatif ('.$item->product?->name.').']);
            }
            $item->forceFill([
                'counted_qty' => $counted === null ? null : Qty::fromMilli($counted),
                'note' => $notes[$item->id] ?? $item->note,
            ])->save();
        }

        return $opname->refresh();
    }

    /** Finalkan: selisih (hasil hitung - stok sistem saat hitung) masuk sebagai mutasi stok + satu jurnal. */
    public function finalizeOpname(StockOpname $opname, ?User $user = null): StockOpname
    {
        return DB::transaction(function () use ($opname, $user) {
            $locked = StockOpname::whereKey($opname->id)->lockForUpdate()->firstOrFail();

            if ($locked->isFinal()) {
                return $locked;
            }

            $warehouseId = $locked->warehouse_id ?: Warehouse::mainId();
            $isMain = $warehouseId === Warehouse::mainId();
            $gain = 0;
            $loss = 0;
            $counted = 0;

            foreach ($locked->items()->whereNotNull('counted_qty')->orderBy('product_id')->get() as $item) {
                $counted++;
                $delta = Qty::toMilli($item->counted_qty) - Qty::toMilli($item->system_qty);
                if ($delta === 0) {
                    continue;
                }

                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                if (! $product) {
                    continue;
                }

                // Penjualan setelah acuan diambil tetap berlaku; stok tidak dipaksa menjadi minus.
                $current = $isMain
                    ? $product->qtyMilli()
                    : Qty::toMilli(DB::table('stock_balances')->where(['product_id' => $product->id, 'warehouse_id' => $warehouseId])->value('qty') ?? 0);
                $delta = max($delta, -$current);
                if ($delta === 0) {
                    continue;
                }

                $this->stock->apply($product->id, $delta, 'opname', $locked, 'admin', $user?->id, 'Opname '.$locked->number, (int) $product->cost, false, $warehouseId);

                $value = Qty::value(abs($delta), (int) $product->cost);
                $delta > 0 ? $gain += $value : $loss += $value;
            }

            if ($counted === 0) {
                throw ValidationException::withMessages(['opname' => 'Belum ada barang yang dihitung.']);
            }

            $this->journals->post('adjustment', 'Stok opname '.$locked->number, [
                ['account' => 'inventory', 'debit' => $gain],
                ['account' => 'inventory_adjustment', 'credit' => $gain],
                ['account' => 'inventory_adjustment', 'debit' => $loss],
                ['account' => 'inventory', 'credit' => $loss],
            ], $locked, $locked->date, $user?->id);

            $locked->forceFill(['status' => 'final', 'finalized_at' => now(), 'value_diff' => $gain - $loss])->save();

            return $locked;
        });
    }

    public function deleteOpname(StockOpname $opname): void
    {
        if ($opname->isFinal()) {
            throw ValidationException::withMessages(['opname' => 'Opname final tidak bisa dihapus.']);
        }

        $opname->delete();
    }
}
