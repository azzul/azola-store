<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Etalase;
use App\Models\ProductGroup;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => url('/'), 'lastmod' => ProductGroup::online()->max('updated_at'), 'priority' => '1.0'],
            ['loc' => route('shop.index'), 'lastmod' => ProductGroup::online()->max('updated_at'), 'priority' => '0.9'],
        ];

        foreach (['about', 'contact', 'faq', 'privacy', 'terms', 'returns'] as $page) {
            $urls[] = ['loc' => route($page), 'lastmod' => null, 'priority' => in_array($page, ['about', 'contact'], true) ? '0.6' : '0.4'];
        }
        if (\App\Models\Testimonial::published()->exists()) {
            $urls[] = ['loc' => route('reviews'), 'lastmod' => \App\Models\Testimonial::published()->max('updated_at'), 'priority' => '0.5'];
        }
        if (\App\Models\Client::published()->exists()) {
            $urls[] = ['loc' => route('clients'), 'lastmod' => \App\Models\Client::published()->max('updated_at'), 'priority' => '0.5'];
        }

        foreach (Category::all() as $category) {
            $last = ProductGroup::online()->where('category_id', $category->id)->max('updated_at');
            if ($last) {
                $urls[] = ['loc' => $category->url(), 'lastmod' => $last, 'priority' => '0.8'];
            }
        }

        foreach (Etalase::visible()->get() as $etalase) {
            $last = ProductGroup::online()->whereHas('etalases', fn ($e) => $e->whereKey($etalase->id))->max('updated_at');
            if ($last) {
                $urls[] = ['loc' => $etalase->url(), 'lastmod' => $last, 'priority' => '0.7'];
            }
        }

        foreach (ProductGroup::online()->orderBy('id')->get(['slug', 'updated_at']) as $group) {
            $urls[] = ['loc' => route('shop.product', $group->slug), 'lastmod' => $group->updated_at, 'priority' => '0.7'];
        }

        if (\Illuminate\Support\Facades\Route::has('pricelist')) {
            $urls[] = ['loc' => route('pricelist'), 'lastmod' => ProductGroup::online()->max('updated_at'), 'priority' => '0.6'];
        }
        if (\Illuminate\Support\Facades\Route::has('articles.index') && \Illuminate\Support\Facades\Schema::hasTable('articles')) {
            $urls[] = ['loc' => route('articles.index'), 'lastmod' => \App\Models\Article::published()->max('updated_at'), 'priority' => '0.6'];
            foreach (\App\Models\Article::published()->get(['slug', 'updated_at']) as $article) {
                $urls[] = ['loc' => route('articles.show', $article->slug), 'lastmod' => $article->updated_at, 'priority' => '0.6'];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '<url><loc>'.e($u['loc']).'</loc>';
            if ($u['lastmod']) {
                $xml .= '<lastmod>'.\Illuminate\Support\Carbon::parse($u['lastmod'])->toAtomString().'</lastmod>';
            }
            $xml .= '<priority>'.$u['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /keranjang\nDisallow: /checkout\nDisallow: /pesanan/\nDisallow: /akun\nDisallow: /masuk\nDisallow: /daftar\nAllow: /\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
