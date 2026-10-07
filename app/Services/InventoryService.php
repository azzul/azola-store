<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stok masuk, penyesuaian, dan opname. Selalu mengubah stok DAN membentuk jurnal sekaligus.
 */
class InventoryService
{
    /** Sumber dana stok masuk -> kunci akun. */
    public const FUNDING = [
        'cash' => 'cash',        // dibayar tunai
        'bank' => 'bank',        // dibayar transfer
        'payable' => 'payable',  // utang ke pemasok
        'equity' => 'equity',    // stok awal / setoran modal
    ];

    public function __construct(
        private StockService $stock,
        private JournalService $journals,
        private CostingService $costing,
    ) {}

    public function receive(
        Product $product,
        string|int|float $qty,
        int $unitCost,
        string $funding = 'cash',
        ?int $userId = null,
        string $source = 'admin',
        ?string $note = null,
        ?int $warehouseId = null,
    ): StockMovement {
        $milli = Qty::toMilli($qty);

        if ($milli <= 0) {
            throw ValidationException::withMessages(['qty' => 'Jumlah stok masuk harus lebih dari 0.']);
        }
        if ($unitCost < 0) {
            throw ValidationException::withMessages(['unit_cost' => 'Harga beli tidak boleh negatif.']);
        }
        if (! isset(self::FUNDING[$funding])) {
            throw ValidationException::withMessages(['funding' => 'Sumber dana tidak dikenal.']);
        }

        return DB::transaction(function () use ($product, $milli, $unitCost, $funding, $userId, $source, $note, $warehouseId) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $value = Qty::value($milli, $unitCost);

            // HPP rata-rata tertimbang seluruh gudang; stok minus/nol tidak ikut menimbang.
            $this->costing->revalue($locked, $milli, $value);

            $movement = $this->stock->apply(
                $locked->id, $milli, 'purchase', null, $source, $userId, $note, $unitCost, false, $warehouseId,
            );

            $this->journals->post(
                'purchase',
                'Stok masuk '.$locked->name.' ('.Qty::pretty(Qty::fromMilli($milli)).' '.$locked->unit.')',
                [
                    ['account' => 'inventory', 'debit' => $value],
                    ['account' => self::FUNDING[$funding], 'credit' => $value],
                ],
                $movement,
                null,
                $userId,
            );

            return $movement;
        });
    }

    /** Tambah/kurangi stok dengan selisih tertentu (rusak, hilang, ketemu). */
    public function adjust(
        Product $product,
        string|int|float $deltaQty,
        ?string $note = null,
        ?int $userId = null,
        string $source = 'admin',
    ): ?StockMovement {
        return DB::transaction(function () use ($product, $deltaQty, $note, $userId, $source) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            return $this->adjustMilli($locked, Qty::toMilli($deltaQty), 'adjustment', $note, $userId, $source);
        });
    }

    /** Opname: set stok ke hasil hitung fisik. Selisih dicatat sebagai mutasi + jurnal. */
    public function opname(
        Product $product,
        string|int|float $countedQty,
        ?string $note = null,
        ?int $userId = null,
        string $source = 'admin',
    ): ?StockMovement {
        $counted = Qty::toMilli($countedQty);

        if ($counted < 0) {
            throw ValidationException::withMessages(['counted' => 'Hasil hitung tidak boleh negatif.']);
        }

        return DB::transaction(function () use ($product, $counted, $note, $userId, $source) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            return $this->adjustMilli($locked, $counted - $locked->qtyMilli(), 'opname', $note, $userId, $source);
        });
    }

    private function adjustMilli(Product $locked, int $deltaMilli, string $type, ?string $note, ?int $userId, string $source): ?StockMovement
    {
        if ($deltaMilli === 0) {
            return null;
        }

        $movement = $this->stock->apply($locked->id, $deltaMilli, $type, null, $source, $userId, $note);

        $value = Qty::value(abs($deltaMilli), (int) $locked->cost);
        $label = $type === 'opname' ? 'Opname' : 'Penyesuaian stok';

        $this->journals->post(
            'adjustment',
            $label.' '.$locked->name.' ('.($deltaMilli > 0 ? '+' : '-').Qty::pretty(Qty::fromMilli(abs($deltaMilli))).')',
            $deltaMilli > 0
                ? [['account' => 'inventory', 'debit' => $value], ['account' => 'inventory_adjustment', 'credit' => $value]]
                : [['account' => 'inventory_adjustment', 'debit' => $value], ['account' => 'inventory', 'credit' => $value]],
            $movement,
            null,
            $userId,
        );

        return $movement;
    }
}
