<?php

namespace App\Support;

use App\Models\Product;
use App\Models\UnitConversion;

/** Data awal untuk editor baris barang (public/js/lines.js). */
final class LineEditor
{
    /** Satu produk dalam bentuk yang dipakai JS: satuan, konversi, harga beli/jual, stok. */
    public static function product(Product $p, ?int $levelId = null): array
    {
        $units = [['unit' => $p->unit, 'factor' => 1, 'price' => $p->priceFor($levelId)]];
        foreach ($p->unitConversions as $c) {
            $units[] = ['unit' => $c->unit, 'factor' => (float) $c->factor, 'price' => $c->price !== null ? (int) $c->price : (int) round($p->priceFor($levelId) * (float) $c->factor)];
        }

        return [
            'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name.($p->variant_name ? ' - '.$p->variant_name : ''),
            'unit' => $p->unit, 'cost' => (int) $p->cost, 'price' => $p->priceFor($levelId),
            'stock' => Qty::pretty($p->stock_qty), 'units' => $units,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  baris form (product_id, unit, qty, price, ...)
     * @return array<int, array<string, mixed>>
     */
    public static function hydrate(array $rows): array
    {
        $ids = collect($rows)->pluck('product_id')->filter()->unique();
        $products = Product::with('unitConversions')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($rows)->filter(fn ($r) => isset($products[$r['product_id'] ?? 0]))->map(fn ($r) => [
            'product' => self::product($products[$r['product_id']]),
            'unit' => $r['unit'] ?? $products[$r['product_id']]->unit,
            'qty' => $r['qty'] ?? $r['qty_change'] ?? '',
            'price' => $r['price'] ?? '',
            'note' => $r['note'] ?? '',
        ])->values()->all();
    }
}
