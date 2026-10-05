<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Str;

/** Meta tag dan data terstruktur (schema.org) untuk halaman toko. */
final class Seo
{
    /**
     * @param  array{title?: string, description?: string, canonical?: string, robots?: string, image?: string|null, type?: string, jsonld?: array}  $options
     */
    public static function page(array $options = []): array
    {
        $name = config('store.name');
        $title = isset($options['title']) ? $options['title'].' | '.$name : $name.' | '.config('store.tagline');

        return [
            'title' => $title,
            'description' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($options['description'] ?? config('store.tagline')))), 158, ''),
            'canonical' => $options['canonical'] ?? url()->current(),
            'robots' => $options['robots'] ?? 'index,follow,max-image-preview:large',
            'image' => $options['image'] ?? null,
            'type' => $options['type'] ?? 'website',
            'jsonld' => $options['jsonld'] ?? [],
        ];
    }

    public static function store(): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => config('store.name'),
            'url' => url('/'),
            'description' => config('store.tagline'),
        ];

        if ($address = config('store.address')) {
            $data['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $address, 'addressCountry' => 'ID'];
        }
        if ($wa = config('store.whatsapp')) {
            $data['telephone'] = '+'.ltrim($wa, '+');
        }
        if ($email = config('store.email')) {
            $data['email'] = $email;
        }

        return $data;
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('store.name'),
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('shop.index').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function product(Product $product): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => Str::limit(strip_tags($product->description ?: $product->name), 300, ''),
            'offers' => [
                '@type' => 'Offer',
                'url' => $product->url(),
                'priceCurrency' => 'IDR',
                'price' => (string) (int) $product->price,
                'itemCondition' => 'https://schema.org/NewCondition',
                // Mengikuti stok realtime: Google melihat status yang sama dengan pembeli.
                'availability' => $product->isInStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ];

        if ($product->barcode) {
            $data['gtin'] = $product->barcode;
        }
        if ($image = $product->imageUrl()) {
            $data['image'] = [url($image)];
        }
        if ($product->category) {
            $data['category'] = $product->category->name;
        }

        return $data;
    }

    /** @param  array<int, array{0: string, 1: string}>  $trail  [[nama, url], ...] */
    public static function breadcrumbs(array $trail): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($trail)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $item[1],
            ])->all(),
        ];
    }

    public static function faq(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($items)->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
            ])->all(),
        ];
    }
}
