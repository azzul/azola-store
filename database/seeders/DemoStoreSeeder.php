<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

/**
 * Data contoh supaya template langsung terlihat hidup. Stok awal dimasukkan lewat
 * InventoryService, jadi mutasi stok dan jurnalnya ikut terbentuk dan rekonsiliasi langsung hijau.
 * Hapus/ganti sebelum dipakai klien.
 */
class DemoStoreSeeder extends Seeder
{
    public function run(InventoryService $inventory): void
    {
        if (Product::exists()) {
            return;
        }

        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $catalog = [
            'Sembako' => [
                'Kebutuhan dapur sehari-hari.',
                [
                    ['SMB-001', 'Beras Premium 5 kg', 'karung', 78000, 68000, 40, true],
                    ['SMB-002', 'Minyak Goreng 2 liter', 'pouch', 38000, 33000, 60, true],
                    ['SMB-003', 'Gula Pasir 1 kg', 'kg', 17500, 15000, 80, false],
                    ['SMB-004', 'Telur Ayam', 'kg', 29000, 26000, 25.5, false],
                ],
            ],
            'Minuman' => [
                'Kopi, teh, dan minuman siap seduh.',
                [
                    ['MNM-001', 'Kopi Bubuk Robusta 200 g', 'bungkus', 24000, 17000, 50, true],
                    ['MNM-002', 'Teh Celup Melati isi 25', 'box', 9500, 6800, 90, false],
                    ['MNM-003', 'Air Mineral 600 ml', 'botol', 3500, 2300, 4, false],
                ],
            ],
            'Alat Tulis' => [
                'Perlengkapan sekolah dan kantor.',
                [
                    ['ATK-001', 'Buku Tulis 38 Lembar', 'pcs', 4500, 3000, 120, true],
                    ['ATK-002', 'Pulpen Gel Hitam', 'pcs', 3500, 2200, 150, false],
                    ['ATK-003', 'Kertas HVS A4 70 gr', 'rim', 52000, 44000, 0, false],
                ],
            ],
        ];

        $order = 0;
        foreach ($catalog as $name => [$description, $products]) {
            $category = Category::create([
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'description' => $description,
                'sort_order' => ++$order,
            ]);

            foreach ($products as [$sku, $productName, $unit, $price, $cost, $qty, $featured]) {
                $product = Product::create([
                    'category_id' => $category->id,
                    'sku' => $sku,
                    'name' => $productName,
                    'slug' => Product::uniqueSlug($productName),
                    'description' => $productName.'. Produk contoh: ganti dengan deskripsi asli toko Anda.',
                    'unit' => $unit,
                    'price' => $price,
                    'min_stock' => 10,
                    'is_active' => true,
                    'is_online' => true,
                    'is_featured' => $featured,
                ]);

                if ($qty > 0) {
                    $inventory->receive($product, (string) $qty, $cost, 'equity', $admin?->id, 'admin', 'Stok awal');
                } else {
                    $product->forceFill(['cost' => $cost])->save();
                }
            }
        }
    }
}
