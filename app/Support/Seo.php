<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductGroup;
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

    public static function product(ProductGroup $group): array
    {
        $variants = $group->sellable();
        $offer = fn (Product $p) => [
            '@type' => 'Offer',
            'url' => $group->url(),
            'priceCurrency' => 'IDR',
            'price' => (string) (int) $p->price,
            'itemCondition' => 'https://schema.org/NewCondition',
            // Mengikuti stok realtime: Google melihat status yang sama dengan pembeli.
            'availability' => $p->isInStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        ];

        $data = [
            '@context' => 'https://schema.org',
            '@type' => $variants->count() > 1 ? 'ProductGroup' : 'Product',
            'name' => $group->name,
            'description' => Str::limit(strip_tags($group->summary ?: ($group->description ?: $group->name)), 300, ''),
        ];

        if ($group->brand) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $group->brand];
        }
        if ($images = $group->images->map(fn ($i) => url($i->url('large')))->all()) {
            $data['image'] = $images;
        }
        if ($group->category) {
            $data['category'] = $group->category->name;
        }

        if ($one = $group->solo()) {
            $data['sku'] = $one->sku;
            if ($one->barcode) {
                $data['gtin'] = $one->barcode;
            }
            $data['offers'] = $offer($one);
        } else {
            $data['productGroupID'] = (string) $group->id;
            $data['variesBy'] = collect($group->axes())->pluck('name')->map(fn ($n) => 'https://schema.org/'.Str::camel($n))->filter(fn ($u) => in_array($u, ['https://schema.org/size', 'https://schema.org/color'], true))->values()->all() ?: null;
            $data['hasVariant'] = $variants->map(fn (Product $p) => array_filter([
                '@type' => 'Product',
                'name' => $p->name,
                'sku' => $p->sku,
                'gtin' => $p->barcode ?: null,
                'offers' => $offer($p),
            ]))->all();
            $data = array_filter($data, fn ($v) => $v !== null);
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
