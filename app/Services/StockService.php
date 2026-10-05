<?php

namespace App\Services;

use App\Events\StockChanged;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
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
    ): StockMovement {
        return DB::transaction(function () use ($productId, $deltaMilli, $type, $reference, $source, $userId, $note, $unitCost, $allowNegative) {
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();

            $before = $product->qtyMilli();
            $after = $before + $deltaMilli;

            if ($after < 0 && ! $allowNegative) {
                throw new InsufficientStockException($product, -$deltaMilli, $before);
            }

            $product->forceFill(['stock_qty' => Qty::fromMilli($after)])->save();

            $movement = StockMovement::create([
                'product_id' => $product->id,
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

            StockChanged::dispatch($product->id, Qty::fromMilli($after), $movement->id);

            return $movement;
        });
    }
}
