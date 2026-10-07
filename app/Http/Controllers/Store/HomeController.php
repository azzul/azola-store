<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Client;
use App\Models\Testimonial;
use App\Models\Etalase;
use App\Models\ProductGroup;
use App\Support\Seo;

class HomeController extends Controller
{
    public function index()
    {
        $featured = ProductGroup::online()->with(['category', 'images', 'variants'])
            ->orderByDesc('is_featured')->orderByDesc('id')
            ->limit(8)->get();

        $categories = Category::query()
            ->withCount(['groups as online_products_count' => fn ($q) => $q->online()])
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
            'etalases' => Etalase::visible()->whereHas('groups', fn ($g) => $g->online())->orderBy('sort_order')->get(),
            'faq' => config('store.faq'),
            'stats' => [
                'products' => ProductGroup::online()->count(),
                'categories' => $categories->count(),
            ],
            'reviewSummary' => Testimonial::summary(),
            'why' => config('store.why'),
            'homeReviews' => Testimonial::published()->where('rating', '>=', 4)->latest('id')->limit(6)->get(),
            'homeClients' => Client::published()->limit(8)->get(),
        ]);
    }
}
