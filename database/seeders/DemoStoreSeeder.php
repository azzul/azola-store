<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Etalase;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Images;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data contoh supaya template langsung terlihat hidup: kategori, etalase, produk dengan variasi (SKU sendiri-sendiri),
 * dan foto ilustrasi. Stok awal masuk lewat InventoryService, jadi mutasi stok dan jurnalnya ikut terbentuk
 * dan rekonsiliasi langsung hijau. Hapus/ganti sebelum dipakai klien.
 */
class DemoStoreSeeder extends Seeder
{
    public function run(InventoryService $inventory): void
    {
        if (Product::exists()) {
            return;
        }

        $admin = User::where('role', User::ROLE_ADMIN)->first();

        /*
         * Variasi: [sku, nama variasi, satuan, harga, HPP, stok, opsi]
         * Group: nama, kategori, merek, ringkasan, deskripsi, poin keunggulan, spesifikasi, nama opsi, etalase, foto, pilihan beranda
         */
        $catalog = [
            'Sembako' => [
                'desc' => 'Kebutuhan dapur sehari-hari.',
                'groups' => [
                    [
                        'name' => 'Beras Premium', 'brand' => 'Azola Pangan', 'image' => 'beras-premium', 'featured' => true,
                        'summary' => 'Beras pulen, bersih, dan wangi dari panen musim ini.',
                        'description' => "Beras premium kualitas pilihan, bulirnya utuh dan bersih sehingga tidak perlu dicuci berkali-kali. Pulen saat dimasak, tetap enak sampai hangat.\n\nTersedia kemasan karung 5 kg, 10 kg, dan 25 kg. Cocok untuk keluarga maupun usaha makan.",
                        'highlights' => ['Bulir utuh, kadar patah rendah', 'Pulen dan wangi alami', 'Kemasan kedap, tahan lembap'],
                        'specs' => [['Asal', 'Jawa Tengah'], ['Kadar air', 'maks. 14%'], ['Penyimpanan', 'Tempat sejuk dan kering']],
                        'options' => ['Kemasan'], 'etalases' => ['terlaris', 'kebutuhan-dapur'],
                        'variants' => [
                            ['SMB-001', '5 kg', 'karung', 78000, 68000, 40, ['Kemasan' => '5 kg']],
                            ['SMB-001-10', '10 kg', 'karung', 152000, 134000, 22, ['Kemasan' => '10 kg']],
                            ['SMB-001-25', '25 kg', 'karung', 372000, 330000, 3, ['Kemasan' => '25 kg']],
                        ],
                    ],
                    [
                        'name' => 'Minyak Goreng', 'brand' => 'Azola Pangan', 'image' => 'minyak-goreng', 'featured' => true,
                        'summary' => 'Minyak goreng jernih, tidak cepat berbusa.',
                        'description' => "Minyak goreng sawit yang jernih dan tidak berbau. Cocok untuk menumis maupun menggoreng.\n\nPilih kemasan sesuai kebutuhan: botol 1 liter untuk rumah tangga, pouch 2 liter, atau jeriken 5 liter untuk usaha.",
                        'highlights' => ['Jernih dan tidak berbau', 'Tahan panas untuk menggoreng', 'Tutup rapat, tidak bocor'],
                        'specs' => [['Bahan', 'Minyak sawit'], ['Kedaluwarsa', '12 bulan sejak produksi']],
                        'options' => ['Isi'], 'etalases' => ['promo', 'kebutuhan-dapur'],
                        'variants' => [
                            ['SMB-002-1', '1 liter', 'botol', 20500, 18000, 50, ['Isi' => '1 liter']],
                            ['SMB-002', '2 liter', 'pouch', 38000, 33000, 60, ['Isi' => '2 liter']],
                            ['SMB-002-5', '5 liter', 'jeriken', 92000, 82000, 12, ['Isi' => '5 liter']],
                        ],
                    ],
                    [
                        'name' => 'Gula Pasir 1 kg', 'brand' => 'Azola Pangan', 'image' => 'gula-pasir',
                        'summary' => 'Gula pasir putih bersih, butiran halus.',
                        'description' => 'Gula pasir putih kemasan 1 kg. Larut cepat untuk minuman dan kue.',
                        'highlights' => ['Butiran halus dan bersih', 'Larut cepat'],
                        'specs' => [['Berat bersih', '1 kg']],
                        'options' => [], 'etalases' => ['kebutuhan-dapur'],
                        'variants' => [['SMB-003', null, 'kg', 17500, 15000, 80, []]],
                    ],
                    [
                        'name' => 'Telur Ayam', 'brand' => null, 'image' => 'telur-ayam',
                        'summary' => 'Telur ayam negeri segar, dijual per kilo.',
                        'description' => 'Telur ayam negeri segar dari peternak mitra. Dijual per kilogram, jadi bisa dibeli sesuai kebutuhan.',
                        'highlights' => ['Segar, dikirim dari peternak mitra'],
                        'specs' => [['Isi per kg', 'sekitar 16 butir']],
                        'options' => [], 'etalases' => ['kebutuhan-dapur'],
                        'variants' => [['SMB-004', null, 'kg', 29000, 26000, 25.5, []]],
                    ],
                ],
            ],
            'Minuman' => [
                'desc' => 'Kopi, teh, dan minuman siap seduh.',
                'groups' => [
                    [
                        'name' => 'Kopi Bubuk Robusta', 'brand' => 'Kopi Pandanaran', 'image' => 'kopi-bubuk-robusta', 'featured' => true,
                        'summary' => 'Robusta sangrai medium, pilih berat dan tingkat gilingan.',
                        'description' => "Biji robusta pilihan disangrai medium lalu digiling. Rasa tebal, pahit seimbang, dan wangi.\n\nPilih gilingan halus untuk kopi tubruk dan espresso, atau kasar untuk seduh manual dan french press.",
                        'highlights' => ['Disangrai dalam batch kecil', 'Dua pilihan gilingan', 'Kemasan zip, aroma tahan lama'],
                        'specs' => [['Jenis', 'Robusta'], ['Sangrai', 'Medium'], ['Asal', 'Temanggung']],
                        'options' => ['Berat', 'Gilingan'], 'etalases' => ['terlaris', 'promo'],
                        'variants' => [
                            ['MNM-001-100H', '100 g halus', 'bungkus', 13000, 9000, 40, ['Berat' => '100 g', 'Gilingan' => 'Halus']],
                            ['MNM-001-100K', '100 g kasar', 'bungkus', 13000, 9000, 30, ['Berat' => '100 g', 'Gilingan' => 'Kasar']],
                            ['MNM-001', '200 g halus', 'bungkus', 24000, 17000, 50, ['Berat' => '200 g', 'Gilingan' => 'Halus']],
                            ['MNM-001-200K', '200 g kasar', 'bungkus', 24000, 17000, 0, ['Berat' => '200 g', 'Gilingan' => 'Kasar']],
                            ['MNM-001-500H', '500 g halus', 'bungkus', 55000, 40000, 18, ['Berat' => '500 g', 'Gilingan' => 'Halus']],
                            ['MNM-001-500K', '500 g kasar', 'bungkus', 55000, 40000, 9, ['Berat' => '500 g', 'Gilingan' => 'Kasar']],
                        ],
                    ],
                    [
                        'name' => 'Teh Celup Melati', 'brand' => 'Teh Nusantara', 'image' => 'teh-celup-melati',
                        'summary' => 'Teh melati wangi, isi 25 kantong.',
                        'description' => 'Teh hitam dengan aroma melati yang lembut. Satu kotak berisi 25 kantong celup.',
                        'highlights' => ['Wangi melati alami', 'Isi 25 kantong'],
                        'specs' => [['Isi', '25 kantong']],
                        'options' => [], 'etalases' => ['terlaris'],
                        'variants' => [['MNM-002', null, 'box', 9500, 6800, 90, []]],
                    ],
                    [
                        'name' => 'Air Mineral 600 ml', 'brand' => 'Mata Air', 'image' => 'air-mineral',
                        'summary' => 'Air mineral botol 600 ml.',
                        'description' => 'Air mineral kemasan botol 600 ml. Segar, praktis dibawa.',
                        'highlights' => ['Botol 600 ml, mudah dibawa'],
                        'specs' => [['Isi', '600 ml']],
                        'options' => [], 'etalases' => [],
                        'variants' => [['MNM-003', null, 'botol', 3500, 2300, 4, []]],
                    ],
                ],
            ],
            'Alat Tulis' => [
                'desc' => 'Perlengkapan sekolah dan kantor.',
                'groups' => [
                    [
                        'name' => 'Buku Tulis 38 Lembar', 'brand' => 'Sinar Kertas', 'image' => 'buku-tulis', 'featured' => true,
                        'summary' => 'Buku tulis bergaris, kertas halus tidak tembus.',
                        'description' => "Buku tulis 38 lembar bergaris, kertas halus dan tidak tembus tinta. Sampul tebal tidak mudah lecek.\n\nPilih warna sampul favorit.",
                        'highlights' => ['Kertas tidak tembus tinta', 'Sampul tebal', 'Tiga warna sampul'],
                        'specs' => [['Isi', '38 lembar'], ['Ukuran', 'A5 (sekitar 15 x 21 cm)']],
                        'options' => ['Warna'], 'etalases' => ['sekolah', 'terlaris'],
                        'variants' => [
                            ['ATK-001', 'Biru', 'pcs', 4500, 3000, 120, ['Warna' => 'Biru']],
                            ['ATK-001-HJ', 'Hijau', 'pcs', 4500, 3000, 80, ['Warna' => 'Hijau']],
                            ['ATK-001-MR', 'Merah', 'pcs', 4500, 3000, 60, ['Warna' => 'Merah']],
                        ],
                    ],
                    [
                        'name' => 'Pulpen Gel', 'brand' => 'Sinar Kertas', 'image' => 'pulpen-gel',
                        'summary' => 'Tinta gel mengalir lancar, ujung 0.5 mm.',
                        'description' => 'Pulpen gel dengan ujung 0.5 mm. Tulisan rapi, tinta cepat kering dan tidak belepotan.',
                        'highlights' => ['Ujung 0.5 mm', 'Tinta cepat kering'],
                        'specs' => [['Ujung', '0.5 mm']],
                        'options' => ['Warna tinta'], 'etalases' => ['sekolah'],
                        'variants' => [
                            ['ATK-002', 'Hitam', 'pcs', 3500, 2200, 150, ['Warna tinta' => 'Hitam']],
                            ['ATK-002-BR', 'Biru', 'pcs', 3500, 2200, 90, ['Warna tinta' => 'Biru']],
                            ['ATK-002-MR', 'Merah', 'pcs', 3500, 2200, 7, ['Warna tinta' => 'Merah']],
                        ],
                    ],
                    [
                        'name' => 'Kertas HVS A4', 'brand' => 'Sinar Kertas', 'image' => 'kertas-hvs-a4',
                        'summary' => 'Kertas HVS A4 putih, 500 lembar per rim.',
                        'description' => 'Kertas HVS A4 untuk cetak dan fotokopi. Putih cerah, tidak macet di mesin.',
                        'highlights' => ['500 lembar per rim', 'Aman untuk printer dan fotokopi'],
                        'specs' => [['Ukuran', 'A4 (210 x 297 mm)'], ['Isi', '500 lembar']],
                        'options' => ['Gramasi'], 'etalases' => ['promo'],
                        'variants' => [
                            ['ATK-003', '70 gr', 'rim', 52000, 44000, 0, ['Gramasi' => '70 gr']],
                            ['ATK-003-80', '80 gr', 'rim', 58000, 49000, 14, ['Gramasi' => '80 gr']],
                        ],
                    ],
                ],
            ],
        ];

        $etalases = [];
        foreach ([
            ['Terlaris', 'terlaris', 'Paling sering dibeli pelanggan kami.'],
            ['Promo minggu ini', 'promo', 'Harga spesial selama persediaan ada.'],
            ['Kebutuhan dapur', 'kebutuhan-dapur', 'Bahan pokok untuk masak sehari-hari.'],
            ['Perlengkapan sekolah', 'sekolah', 'Alat tulis dan kertas untuk sekolah dan kantor.'],
        ] as $i => [$name, $slug, $desc]) {
            $etalases[$slug] = Etalase::create(['name' => $name, 'slug' => $slug, 'description' => $desc, 'sort_order' => $i + 1]);
        }

        $order = 0;
        foreach ($catalog as $categoryName => $cat) {
            $category = Category::create([
                'name' => $categoryName, 'slug' => Str::slug($categoryName), 'description' => $cat['desc'], 'sort_order' => ++$order,
            ]);

            foreach ($cat['groups'] as $g) {
                $single = count($g['variants']) === 1;
                $group = ProductGroup::create([
                    'category_id' => $category->id, 'name' => $g['name'], 'slug' => ProductGroup::uniqueSlug($g['name']),
                    'brand' => $g['brand'], 'summary' => $g['summary'], 'description' => $g['description'],
                    'highlights' => $g['highlights'], 'specs' => array_map(fn ($s) => ['label' => $s[0], 'value' => $s[1]], $g['specs']),
                    'option_names' => $g['options'] ?: null, 'auto' => false,
                    'is_active' => true, 'is_online' => true, 'is_featured' => $g['featured'] ?? false,
                ]);
                $group->etalases()->sync(array_map(fn ($s) => $etalases[$s]->id, $g['etalases']));

                foreach ($g['variants'] as $n => [$sku, $variant, $unit, $price, $cost, $qty, $options]) {
                    $name = $variant ? $g['name'].' '.$variant : $g['name'];
                    $product = Product::create([
                        'group_id' => $group->id, 'category_id' => $category->id, 'sku' => $sku,
                        'name' => $name, 'slug' => Product::uniqueSlug($name), 'variant_name' => $variant, 'options' => $options ?: null,
                        'sort_order' => $n, 'description' => $g['summary'], 'unit' => $unit, 'price' => $price, 'min_stock' => 10,
                        'is_active' => true, 'is_online' => true, 'is_featured' => false,
                    ]);

                    if ($qty > 0) {
                        $inventory->receive($product, (string) $qty, $cost, 'equity', $admin?->id, 'admin', 'Stok awal');
                    } else {
                        $product->forceFill(['cost' => $cost])->save();
                    }
                }

                $group->refreshPrices();
                $this->images($group, $g['image'], $g['name']);
            }
        }
    }

    /** Tiga foto per produk: kemasan, close-up, dan semua ukuran. File ada di database/seeders/demo-images. */
    private function images(ProductGroup $group, string $slug, string $name): void
    {
        $alts = ['Kemasan', 'Close-up kemasan', 'Pilihan ukuran'];

        foreach ([1, 2, 3] as $i) {
            $file = database_path("seeders/demo-images/{$slug}-{$i}.jpg");
            if (! is_file($file)) {
                continue;
            }

            ProductImage::create(['product_group_id' => $group->id, 'alt' => "{$name}, ".strtolower($alts[$i - 1]), 'sort_order' => $i] + Images::store($file));
        }
    }
}
