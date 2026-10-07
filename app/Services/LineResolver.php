<?php

namespace App\Services;

use App\Models\Product;
use App\Models\UnitConversion;
use App\Support\Qty;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah baris input form (produk, satuan, jumlah, harga) menjadi baris siap pakai:
 * faktor konversi satuan, jumlah dalam satuan dasar, dan total baris.
 */
class LineResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{product: Product, unit: string, factor: float, qty_milli: int, base_milli: int, price: int, line_total: int}>
     */
    public function resolve(array $items, string $field = 'items'): array
    {
        $items = array_values(array_filter($items, fn ($i) => ! empty($i['product_id']) && Qty::toMilli($i['qty'] ?? 0) !== 0));

        if ($items === []) {
            throw ValidationException::withMessages([$field => 'Tambahkan minimal satu barang dengan jumlah lebih dari 0.']);
        }

        $products = Product::whereIn('id', collect($items)->pluck('product_id')->unique())->get()->keyBy('id');
        $conversions = UnitConversion::whereIn('product_id', $products->keys())->get()->groupBy('product_id');

        $lines = [];
        foreach ($items as $i => $item) {
            $product = $products[(int) $item['product_id']] ?? null;
            $row = $i + 1;

            if (! $product) {
                throw ValidationException::withMessages([$field => "Baris {$row}: produk tidak ditemukan."]);
            }

            $qty = Qty::toMilli($item['qty']);
            if ($qty <= 0) {
                throw ValidationException::withMessages([$field => "Baris {$row} ({$product->name}): jumlah harus lebih dari 0."]);
            }

            $unit = trim((string) ($item['unit'] ?? '')) ?: $product->unit;
            $factor = 1.0;
            if ($unit !== $product->unit) {
                $conversion = ($conversions[$product->id] ?? collect())->firstWhere('unit', $unit);
                if (! $conversion) {
                    throw ValidationException::withMessages([$field => "Baris {$row} ({$product->name}): satuan {$unit} belum punya konversi."]);
                }
                $factor = (float) $conversion->factor;
            }

            $price = (int) ($item['price'] ?? 0);
            if ($price < 0) {
                throw ValidationException::withMessages([$field => "Baris {$row} ({$product->name}): harga tidak boleh negatif."]);
            }

            $lines[] = [
                'product' => $product,
                'unit' => $unit,
                'factor' => $factor,
                'qty_milli' => $qty,
                'base_milli' => (int) round($qty * $factor),
                'price' => $price,
                'line_total' => Qty::value($qty, $price),
            ];
        }

        return $lines;
    }

    /** Bagi potongan ke baris secara proporsional; sisa pembulatan ke baris terakhir. Mengembalikan net per baris. */
    public function allocateDiscount(array $lines, int $discount): array
    {
        $subtotal = array_sum(array_column($lines, 'line_total'));
        if ($discount < 0 || $discount > $subtotal) {
            throw ValidationException::withMessages(['discount' => 'Potongan tidak boleh melebihi subtotal.']);
        }

        $left = $discount;
        foreach ($lines as $k => $line) {
            $share = $k === array_key_last($lines)
                ? $left
                : ($subtotal > 0 ? (int) round($discount * $line['line_total'] / $subtotal) : 0);
            $share = min($share, $left, $line['line_total']);
            $left -= $share;
            $lines[$k]['net_total'] = $line['line_total'] - $share;
        }

        // Bila baris terakhir terlalu kecil menampung sisa pembulatan, ambil dari baris lain.
        foreach ($lines as $k => $line) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, $lines[$k]['net_total']);
            $lines[$k]['net_total'] -= $take;
            $left -= $take;
        }

        return $lines;
    }
}
