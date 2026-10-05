<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Seo;

class HomeController extends Controller
{
    public function index()
    {
        $featured = Product::online()->with('category')
            ->orderByDesc('is_featured')->orderByDesc('id')
            ->limit(8)->get();

        $categories = Category::query()
            ->withCount(['products as online_products_count' => fn ($q) => $q->online()])
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($c) => $c->online_products_count > 0)->values();

        $seo = Seo::page([
            'description' => config('store.tagline').' Stok di web selalu sama dengan stok di toko.',
            'canonical' => url('/'),
            'jsonld' => [Seo::store(), Seo::website(), Seo::faq(config('store.faq'))],
        ]);

        return view('store.home', [
            'seo' => $seo,
            'shelf' => $featured->take(5),
            'featured' => $featured,
            'categories' => $categories,
            'faq' => config('store.faq'),
        ]);
    }
}
