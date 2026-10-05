<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi toko (satu file per klien)
|--------------------------------------------------------------------------
| Ganti nilai lewat .env agar template ini bisa dipakai ulang untuk banyak
| klien tanpa mengubah kode.
*/

return [
    'name' => env('STORE_NAME', 'Azola Store'),
    'tagline' => env('STORE_TAGLINE', 'Belanja online, stok selalu sama dengan di toko.'),
    'whatsapp' => env('STORE_WHATSAPP', ''),
    'email' => env('STORE_EMAIL', ''),
    'address' => env('STORE_ADDRESS', ''),

    'bank' => [
        'name' => env('STORE_BANK_NAME', ''),
        'account' => env('STORE_BANK_ACCOUNT', ''),
        'holder' => env('STORE_BANK_HOLDER', ''),
    ],

    'theme' => [
        'brand' => env('STORE_BRAND_COLOR', '#0F5C46'),
        'accent' => env('STORE_ACCENT_COLOR', '#FFD43B'),
    ],

    // PPN/pajak dalam persen, dihitung di atas (subtotal - diskon). 0 = tanpa pajak.
    'tax_rate' => (float) env('STORE_TAX_RATE', 0),

    'shipping' => [
        'flat' => (int) env('STORE_SHIPPING_FLAT', 15000),
        'free_over' => (int) env('STORE_FREE_SHIPPING_OVER', 250000),
    ],

    // Metode pembayaran yang boleh dipilih pembeli di web.
    'web_payment_methods' => [
        'transfer' => 'Transfer bank',
        'qris' => 'QRIS',
        'cod' => 'Bayar di tempat (COD)',
    ],

    // Selisih nilai persediaan (Rp) yang masih dianggap wajar karena pembulatan HPP rata-rata.
    'reconcile_tolerance' => (int) env('STORE_RECONCILE_TOLERANCE', 1000),

    // Barang dengan stok <= ambang ini tampil sebagai "Sisa X" di toko.
    'public_low_stock' => 5,

    'faq' => [
        ['q' => 'Apakah stok di web sama dengan di toko?', 'a' => 'Ya. Web, kasir di toko, dan aplikasi Android memakai satu database stok. Begitu barang terjual di kasir, stok di web ikut berkurang.'],
        ['q' => 'Bagaimana cara membayar?', 'a' => 'Pilih transfer bank, QRIS, atau bayar di tempat saat checkout. Pesanan transfer diproses setelah pembayaran kami konfirmasi.'],
        ['q' => 'Berapa lama pesanan diproses?', 'a' => 'Pesanan yang sudah dibayar diproses di hari kerja yang sama bila masuk sebelum jam tutup toko.'],
        ['q' => 'Bisa ambil sendiri di toko?', 'a' => 'Bisa. Pilih "Ambil di toko" saat checkout dan tidak ada ongkos kirim.'],
    ],
];
