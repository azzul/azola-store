<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Seo;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    private const SORTS = [
        'terbaru' => ['Terbaru', 'id', 'desc'],
        'termurah' => ['Harga terendah', 'price', 'asc'],
        'termahal' => ['Harga tertinggi', 'price', 'desc'],
        'nama' => ['Nama A-Z', 'name', 'asc'],
    ];

    public function index(Request $request)
    {
        return $this->listing($request, null);
    }

    public function category(Request $request, Category $category)
    {
        return $this->listing($request, $category);
    }

    private function listing(Request $request, ?Category $category)
    {
        $q = trim((string) $request->query('q', ''));
        $sortKey = array_key_exists($request->query('urut'), self::SORTS) ? $request->query('urut') : 'terbaru';
        [, $column, $direction] = self::SORTS[$sortKey];

        $query = Product::online()->with('category');

        if ($category) {
            $query->where('category_id', $category->id);
        } elseif ($slug = $request->query('kategori')) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $slug));
        }

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', $q));
        }

        $products = $query->orderBy($column, $direction)->orderBy('id')->paginate(12)->withQueryString();

        // Hasil pencarian/urutan tidak diindeks supaya Google hanya melihat halaman kategori yang bersih.
        $filtered = $q !== '' || $sortKey !== 'terbaru' || (! $category && $request->query('kategori'));
        $page = $products->currentPage();
        $base = $category ? $category->url() : route('shop.index');

        $title = $category ? $category->name : 'Semua produk';
        $trail = [['Beranda', url('/')], ['Produk', route('shop.index')]];
        if ($category) {
            $trail[] = [$category->name, $category->url()];
        }

        $seo = Seo::page([
            'title' => $title.($page > 1 ? " - halaman {$page}" : ''),
            'description' => $category?->description ?: ($category ? "Belanja {$category->name} di ".config('store.name').'. Stok realtime, harga jelas.' : 'Katalog lengkap '.config('store.name').'. Stok realtime, harga jelas.'),
            'canonical' => $page > 1 ? $base.'?page='.$page : $base,
            'robots' => $filtered ? 'noindex,follow' : 'index,follow,max-image-preview:large',
            'jsonld' => [Seo::breadcrumbs($trail)],
        ]);

        return view('store.catalog', [
            'seo' => $seo,
            'products' => $products,
            'category' => $category,
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'q' => $q,
            'sort' => $sortKey,
            'sorts' => collect(self::SORTS)->map(fn ($s) => $s[0]),
            'heading' => $title,
            'trail' => $trail,
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active && $product->is_online, 404);

        $product->load('category');

        $related = Product::online()->with('category')
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->orderByDesc('is_featured')->orderByDesc('id')->limit(4)->get();

        $trail = [['Beranda', url('/')], ['Produk', route('shop.index')]];
        if ($product->category) {
            $trail[] = [$product->category->name, $product->category->url()];
        }
        $trail[] = [$product->name, $product->url()];

        $seo = Seo::page([
            'title' => $product->meta_title ?: $product->name,
            'description' => $product->meta_description ?: ($product->description ?: $product->name.' di '.config('store.name')),
            'canonical' => $product->url(),
            'image' => $product->imageUrl(),
            'type' => 'product',
            'jsonld' => [Seo::product($product), Seo::breadcrumbs($trail)],
        ]);

        return view('store.product', compact('seo', 'product', 'related', 'trail'));
    }
}
