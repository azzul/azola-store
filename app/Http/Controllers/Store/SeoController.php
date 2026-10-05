<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => url('/'), 'lastmod' => Product::online()->max('updated_at'), 'priority' => '1.0'],
            ['loc' => route('shop.index'), 'lastmod' => Product::online()->max('updated_at'), 'priority' => '0.9'],
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

        foreach (Category::withMax(['products as last_update' => fn ($q) => $q->online()], 'updated_at')->get() as $category) {
            $urls[] = ['loc' => $category->url(), 'lastmod' => $category->last_update, 'priority' => '0.8'];
        }

        foreach (Product::online()->orderBy('id')->get(['slug', 'updated_at']) as $product) {
            $urls[] = ['loc' => route('shop.product', $product->slug), 'lastmod' => $product->updated_at, 'priority' => '0.7'];
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
        $body = "User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /keranjang\nDisallow: /checkout\nDisallow: /pesanan/\nAllow: /\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
