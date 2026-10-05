<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feed stok untuk halaman toko (publik): hanya produk online dan hanya status,
 * jumlah persisnya baru tampil saat stok menipis.
 */
class PublicStockController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'integer', 'min:0']]);

        $cursor = (int) StockMovement::max('id');
        $since = $request->query('since');

        if ($since === null) {
            return response()->json(['cursor' => $cursor, 'reload' => false, 'items' => []]);
        }

        $ids = StockMovement::where('id', '>', (int) $since)->where('id', '<=', $cursor)
            ->distinct()->limit(501)->pluck('product_id');

        if ($ids->count() > 500) {
            return response()->json(['cursor' => $cursor, 'reload' => true, 'items' => []]);
        }

        $items = Product::online()->whereIn('id', $ids)->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'state' => $p->stockState(),
            'label' => $p->publicStockLabel(),
            'qty' => $p->stockState() === 'ok' ? null : Qty::pretty($p->stock_qty),
            'available' => $p->isInStock(),
        ])->values();

        return response()->json(['cursor' => $cursor, 'reload' => false, 'items' => $items]);
    }
}
