<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Etalase;
use App\Models\ProductGroup;
use App\Support\Seo;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    private const SORTS = [
        'terbaru' => ['Terbaru', 'id', 'desc'],
        'termurah' => ['Harga terendah', 'price_min', 'asc'],
        'termahal' => ['Harga tertinggi', 'price_min', 'desc'],
        'nama' => ['Nama A-Z', 'name', 'asc'],
    ];

    public function index(Request $request)
    {
        return $this->listing($request, null, null);
    }

    public function category(Request $request, Category $category)
    {
        return $this->listing($request, $category, null);
    }

    public function etalase(Request $request, Etalase $etalase)
    {
        abort_unless($etalase->is_visible, 404);

        return $this->listing($request, null, $etalase);
    }

    private function listing(Request $request, ?Category $category, ?Etalase $etalase)
    {
        $q = trim((string) $request->query('q', ''));
        $sortKey = array_key_exists($request->query('urut'), self::SORTS) ? $request->query('urut') : 'terbaru';
        [, $column, $direction] = self::SORTS[$sortKey];
        $onlyStock = $request->boolean('tersedia');

        $query = ProductGroup::online()->with(['category', 'images', 'variants']);

        if ($category) {
            $query->where('category_id', $category->id);
        } elseif ($slug = $request->query('kategori')) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $slug));
        }

        if ($etalase) {
            $query->whereHas('etalases', fn ($e) => $e->whereKey($etalase->id));
        } elseif ($slug = $request->query('etalase')) {
            $query->whereHas('etalases', fn ($e) => $e->where('slug', $slug));
        }

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('brand', 'like', $like)
                ->orWhereHas('variants', fn ($v) => $v->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', $q)));
        }

        if ($onlyStock) {
            $query->whereHas('variants', fn ($v) => $v->online()->where('stock_qty', '>', 0));
        }

        $groups = $query->orderBy($column, $direction)->orderBy('id')->paginate(12)->withQueryString();

        // Hasil pencarian/urutan/filter tidak diindeks supaya Google hanya melihat halaman kategori dan etalase yang bersih.
        $filtered = $q !== '' || $onlyStock || $sortKey !== 'terbaru'
            || (! $category && $request->query('kategori')) || (! $etalase && $request->query('etalase'));
        $page = $groups->currentPage();
        $current = $etalase ?? $category;
        $base = $current ? $current->url() : route('shop.index');

        $title = $current ? $current->name : 'Semua produk';
        $trail = [['Beranda', url('/')], ['Produk', route('shop.index')]];
        if ($current) {
            $trail[] = [$current->name, $current->url()];
        }

        $description = $current?->description ?: ($current ? "Belanja {$current->name} di ".config('store.name').'. Stok realtime, harga jelas.' : 'Katalog lengkap '.config('store.name').'. Stok realtime, harga jelas.');

        $seo = Seo::page([
            'title' => $title.($page > 1 ? " - halaman {$page}" : ''),
            'description' => $description,
            'canonical' => $page > 1 ? $base.'?page='.$page : $base,
            'robots' => $filtered ? 'noindex,follow' : 'index,follow,max-image-preview:large',
            'jsonld' => [Seo::breadcrumbs($trail)],
        ]);

        return view('store.catalog', [
            'seo' => $seo,
            'products' => $groups,
            'category' => $category,
            'etalase' => $etalase,
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'etalases' => Etalase::visible()->orderBy('sort_order')->orderBy('name')->get(),
            'q' => $q,
            'sort' => $sortKey,
            'onlyStock' => $onlyStock,
            'sorts' => collect(self::SORTS)->map(fn ($s) => $s[0]),
            'heading' => $title,
            'lead' => $current?->description,
            'trail' => $trail,
        ]);
    }

    public function show(ProductGroup $group)
    {
        abort_unless($group->is_active && $group->is_online && $group->sellable()->isNotEmpty(), 404);

        $group->load(['category', 'images', 'etalases' => fn ($e) => $e->visible()]);

        $related = ProductGroup::online()->with(['category', 'images', 'variants'])
            ->where('id', '!=', $group->id)
            ->when($group->category_id, fn ($q) => $q->where('category_id', $group->category_id))
            ->orderByDesc('is_featured')->orderByDesc('id')->limit(4)->get();

        $trail = [['Beranda', url('/')], ['Produk', route('shop.index')]];
        if ($group->category) {
            $trail[] = [$group->category->name, $group->category->url()];
        }
        $trail[] = [$group->name, $group->url()];

        $seo = Seo::page([
            'title' => $group->meta_title ?: $group->name,
            'description' => $group->meta_description ?: ($group->summary ?: ($group->description ?: $group->name.' di '.config('store.name'))),
            'canonical' => $group->url(),
            'image' => ($img = $group->imageUrl('large')) ? url($img) : null,
            'type' => 'product',
            'jsonld' => [Seo::product($group), Seo::breadcrumbs($trail)],
        ]);

        return view('store.product', ['seo' => $seo, 'group' => $group, 'related' => $related, 'trail' => $trail]);
    }
}
