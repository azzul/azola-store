<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /** Batas perubahan per respons. Lewat dari ini klien diminta mengambil snapshot penuh. */
    private const MAX_CHANGES = 5000;

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::orderBy('sort_order')->orderBy('name')->get()->map(Presenter::category(...))]);
    }

    /**
     * Master produk, bisa bertahap: ?updated_since=2026-10-05T10:00:00+07:00&per_page=500.
     * Klien menyimpan server_time dari respons terakhir sebagai updated_since berikutnya.
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $serverTime = now()->toIso8601String();
        $isAdmin = $request->user()->isAdmin();

        $page = Product::query()
            ->when($request->query('updated_since'), fn ($q, $since) => $q->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->paginate((int) $request->query('per_page', 500));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $p) => Presenter::product($p, $isAdmin))->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'server_time' => $serverTime,
            'stock_cursor' => (int) StockMovement::max('id'),
        ]);
    }

    /**
     * Feed stok. Tanpa ?since => snapshot seluruh produk + kursor.
     * Dengan ?since=<kursor> => hanya produk yang stoknya berubah sejak kursor itu.
     * Simpan "cursor" dari respons dan kirim lagi di panggilan berikutnya (polling 3-5 detik cukup).
     */
    public function stock(Request $request): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'integer', 'min:0']]);

        // Kursor dibaca lebih dulu: perubahan yang masuk saat kita membaca produk akan
        // tertangkap lagi pada panggilan berikutnya, tidak ada yang terlewat.
        $cursor = (int) StockMovement::max('id');
        $since = $request->query('since');

        $full = $since === null;
        $ids = null;

        if (! $full) {
            $ids = StockMovement::where('id', '>', (int) $since)->where('id', '<=', $cursor)
                ->distinct()->limit(self::MAX_CHANGES + 1)->pluck('product_id');

            if ($ids->count() > self::MAX_CHANGES) {
                $full = true;
                $ids = null;
            }
        }

        $products = Product::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get(['id', 'sku', 'unit', 'stock_qty', 'min_stock', 'is_active', 'is_online', 'updated_at']);

        return response()->json([
            'cursor' => $cursor,
            'full' => $full,
            'items' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'stock' => \App\Support\Qty::fromMilli($p->qtyMilli()),
                'stock_state' => $p->stockState(),
                'is_active' => (bool) $p->is_active,
            ])->values(),
        ]);
    }
}
