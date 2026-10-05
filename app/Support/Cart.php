<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/** Keranjang berbasis session: [product_id => qty dalam milli]. Harga selalu diambil dari database. */
class Cart
{
    private const KEY = 'cart';

    public function add(int $productId, int $milli): void
    {
        $cart = $this->raw();
        $cart[$productId] = min(($cart[$productId] ?? 0) + $milli, 1_000_000_000);
        $this->save($cart);
    }

    public function set(int $productId, int $milli): void
    {
        $cart = $this->raw();

        if ($milli <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = min($milli, 1_000_000_000);
        }

        $this->save($cart);
    }

    public function remove(int $productId): void
    {
        $this->set($productId, 0);
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
    }

    /** Jumlah jenis barang (untuk lencana di header). */
    public function count(): int
    {
        return count($this->raw());
    }

    /**
     * @return Collection<int, array{product: Product, milli: int, gross: int, short: bool}>
     */
    public function lines(): Collection
    {
        $raw = $this->raw();

        if ($raw === []) {
            return collect();
        }

        $products = Product::online()->whereIn('id', array_keys($raw))->get()->keyBy('id');

        return collect($raw)
            ->map(function (int $milli, int $id) use ($products) {
                $product = $products[$id] ?? null;

                return $product ? [
                    'product' => $product,
                    'milli' => $milli,
                    'gross' => Qty::value($milli, $product->price),
                    'short' => $milli > $product->qtyMilli(),
                ] : null;
            })
            ->filter()
            ->values();
    }

    public function subtotal(): int
    {
        return (int) $this->lines()->sum('gross');
    }

    /** Ongkir untuk metode pengiriman tertentu. */
    public static function shippingFor(string $delivery, int $subtotal): int
    {
        if ($delivery !== 'ship' || $subtotal <= 0) {
            return 0;
        }

        $free = (int) config('store.shipping.free_over');

        return ($free > 0 && $subtotal >= $free) ? 0 : (int) config('store.shipping.flat');
    }

    /** @return array<int, int> */
    private function raw(): array
    {
        return array_map('intval', session(self::KEY, []));
    }

    private function save(array $cart): void
    {
        session([self::KEY => $cart]);
    }
}
