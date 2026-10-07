<?php

namespace App\Services;

use App\Events\StockChanged;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\Qty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu untuk mengubah stok. Setiap perubahan:
 *  1. mengunci baris produk (lockForUpdate) agar dua transaksi bersamaan tidak saling menimpa,
 *  2. menolak stok negatif,
 *  3. mencatat mutasi (stock_movements) dan memperbarui cache stock_qty dalam satu transaksi.
 */
class StockService
{
    public function apply(
        int $productId,
        int $deltaMilli,
        string $type,
        ?Model $reference = null,
        string $source = 'admin',
        ?int $userId = null,
        ?string $note = null,
        ?int $unitCost = null,
        bool $allowNegative = false,
        ?int $warehouseId = null,
    ): StockMovement {
        return DB::transaction(function () use ($productId, $deltaMilli, $type, $reference, $source, $userId, $note, $unitCost, $allowNegative, $warehouseId) {
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();

            $mainId = Warehouse::mainId();
            $warehouseId ??= $mainId;
            $isMain = $warehouseId === $mainId;

            if ($isMain) {
                $before = $product->qtyMilli();
            } else {
                $row = DB::table('stock_balances')->where(['product_id' => $product->id, 'warehouse_id' => $warehouseId])->first();
                $before = Qty::toMilli($row->qty ?? 0);
            }

            $after = $before + $deltaMilli;

            if ($after < 0 && ! $allowNegative) {
                throw new InsufficientStockException($product, -$deltaMilli, $before);
            }

            if ($isMain) {
                // Hanya gudang jual yang memengaruhi stok di toko web dan kasir.
                $product->forceFill(['stock_qty' => Qty::fromMilli($after)])->save();
            } else {
                DB::table('stock_balances')->updateOrInsert(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                    ['qty' => Qty::fromMilli($after)],
                );
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'qty_change' => Qty::fromMilli($deltaMilli),
                'qty_after' => Qty::fromMilli($after),
                'unit_cost' => $unitCost ?? (int) $product->cost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'source' => $source,
                'user_id' => $userId,
                'note' => $note,
            ]);

            if ($isMain) {
                StockChanged::dispatch($product->id, Qty::fromMilli($after), $movement->id);
            }

            return $movement;
        });
    }
}
