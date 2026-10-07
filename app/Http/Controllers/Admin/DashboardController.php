<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ReconciliationService;
use App\Support\Qty;
use App\Support\Rupiah;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(ReconciliationService $reconciliation)
    {
        $active = Order::where('status', '!=', 'cancelled');
        $today = now()->startOfDay();

        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());
        $byDay = (clone $active)->where('ordered_at', '>=', $days->first())
            ->get(['ordered_at', 'grand_total'])
            ->groupBy(fn ($o) => $o->ordered_at->format('Y-m-d'))
            ->map(fn ($rows) => (int) $rows->sum('grand_total'));

        $chart = $days->map(fn ($d) => [
            'label' => $d->translatedFormat('D'),
            'date' => $d->format('d/m'),
            'total' => (int) ($byDay[$d->format('Y-m-d')] ?? 0),
        ])->all();

        $products = Product::where('is_active', true)->select(['id', 'stock_qty', 'min_stock', 'cost'])->withOtherStock()->get();

        return view('admin.dashboard', [
            'todayTotal' => (int) (clone $active)->where('ordered_at', '>=', $today)->sum('grand_total'),
            'todayCount' => (clone $active)->where('ordered_at', '>=', $today)->count(),
            'monthTotal' => (int) (clone $active)->where('ordered_at', '>=', now()->startOfMonth())->sum('grand_total'),
            'pendingWeb' => Order::where('channel', 'web')->where('status', 'pending')->count(),
            'receivable' => (int) (clone $active)->selectRaw('COALESCE(SUM(grand_total - paid_total - returned_total), 0) as v')->value('v'),
            'stockValue' => (int) $products->sum(fn (Product $p) => $p->inventoryValue()),
            'lowCount' => $products->filter(fn (Product $p) => $p->stockState() === 'low')->count(),
            'outCount' => $products->filter(fn (Product $p) => $p->stockState() === 'out')->count(),
            'chart' => $chart,
            'channels' => (clone $active)->where('ordered_at', '>=', now()->startOfMonth())
                ->selectRaw('channel, COUNT(*) as n, SUM(grand_total) as total')->groupBy('channel')->get(),
            'recent' => Order::latest('ordered_at')->latest('id')->limit(6)->get(),
            'reconciliation' => $reconciliation->run(),
        ]);
    }

    /** Pantauan stok realtime semua produk. */
    public function stock(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $filter = $request->query('tampil');

        $products = Product::with('category')->select('products.*')->withOtherStock()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")->orWhere('barcode', $q)))
            ->orderBy('name')->get()
            ->when(in_array($filter, ['low', 'out'], true), fn ($c) => $c->filter(fn (Product $p) => $p->stockState() === $filter))
            ->values();

        return view('admin.stock', [
            'products' => $products,
            'cursor' => (int) StockMovement::max('id'),
            'q' => $q,
            'filter' => $filter,
            'recent' => StockMovement::with('product:id,name,unit')->latest('id')->limit(12)->get(),
        ]);
    }

    /** Polling stok untuk dashboard: hanya produk yang berubah sejak kursor. */
    public function stockFeed(Request $request)
    {
        $request->validate(['since' => ['required', 'integer', 'min:0']]);

        $cursor = (int) StockMovement::max('id');
        $ids = StockMovement::where('id', '>', (int) $request->query('since'))->where('id', '<=', $cursor)
            ->distinct()->limit(501)->pluck('product_id');

        if ($ids->count() > 500) {
            return response()->json(['cursor' => $cursor, 'reload' => true, 'items' => []]);
        }

        $items = Product::whereIn('id', $ids)->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'qty' => Qty::pretty($p->stock_qty),
            'state' => $p->stockState(),
            'label' => ['ok' => 'Aman', 'low' => 'Menipis', 'out' => 'Habis'][$p->stockState()],
            'value' => Rupiah::format($p->inventoryValue()),
        ])->values();

        return response()->json(['cursor' => $cursor, 'reload' => false, 'items' => $items]);
    }
}
