<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\AccountSeeder;

trait CreatesStoreData
{
    protected function seedAccounts(): void
    {
        $this->seed(AccountSeeder::class);
    }

    protected function makeProduct(array $attributes = []): Product
    {
        static $n = 0;
        $n++;

        $name = $attributes['name'] ?? "Produk Uji {$n}";

        return Product::create(array_merge([
            'sku' => "SKU-{$n}",
            'name' => $name,
            'slug' => Product::uniqueSlug($name),
            'price' => 10000,
            'unit' => 'pcs',
            'is_active' => true,
            'is_online' => true,
        ], $attributes));
    }

    /** Produk dengan stok awal lewat jalur resmi (mutasi + jurnal), HPP = $cost. */
    protected function stockedProduct(string $qty = '10', int $cost = 6000, array $attributes = []): Product
    {
        $product = $this->makeProduct($attributes);
        app(InventoryService::class)->receive($product, $qty, $cost, 'equity');

        return $product->fresh();
    }

    protected function makeUser(string $role = 'admin'): User
    {
        static $n = 0;
        $n++;

        $user = User::factory()->create(['email' => "u{$n}@example.test"]);
        $user->withRole($role)->save();

        return $user;
    }
}
