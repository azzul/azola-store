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

    'instagram' => env('STORE_INSTAGRAM', ''),   // username tanpa @
    'facebook' => env('STORE_FACEBOOK', ''),     // URL halaman
    'tiktok' => env('STORE_TIKTOK', ''),         // username tanpa @
    'map_url' => env('STORE_MAP_URL', ''),       // tautan Google Maps lokasi toko

    // Jam buka: [hari, jam]. Tampil di halaman Kontak.
    'hours' => [
        ['Senin - Sabtu', '08.00 - 17.00'],
        ['Minggu', 'Tutup'],
    ],

    // Isi halaman Tentang Kami. Ganti dengan cerita toko klien.
    'about' => [
        'since' => env('STORE_SINCE', ''), // tahun berdiri, mis. 2015. Kosong = disembunyikan
        'headline' => 'Toko lokal dengan stok yang bisa dipercaya.',
        'story' => [
            'Kami toko yang melayani kebutuhan sehari-hari pelanggan di sekitar kami. Website ini dibuat supaya belanja bisa dilakukan dari rumah tanpa takut barangnya ternyata habis.',
            'Semua penjualan di toko, di kasir, dan di website tercatat di satu sistem. Karena itu stok yang kamu lihat di sini sama dengan stok di rak.',
        ],
        'values' => [
            ['Stok jujur', 'Yang tampil tersedia memang ada. Kalau habis, labelnya langsung berubah.'],
            ['Harga sama', 'Harga di website sama dengan harga di toko, tanpa selisih diam-diam.'],
            ['Dilayani orang', 'Ada pertanyaan atau kendala pesanan, kamu bicara dengan tim toko, bukan mesin.'],
        ],
    ],

    // Bagian "Kenapa kami" di beranda. key = nama ikon (stock, price, pay, pickup, help, safe).
    'why' => [
        ['key' => 'stock', 'title' => 'Stok jujur dan realtime', 'text' => 'Web, kasir toko, dan aplikasi Android memakai satu stok. Barang yang tampil tersedia memang ada di rak.'],
        ['key' => 'price', 'title' => 'Harga sama dengan di toko', 'text' => 'Tidak ada harga khusus online yang diam-diam lebih mahal. Yang kamu lihat di sini, itu juga harga di kasir.'],
        ['key' => 'pay', 'title' => 'Bayar sesuai kebiasaanmu', 'text' => 'Transfer bank, QRIS, atau bayar di tempat. Pesanan transfer diproses begitu pembayaran kami konfirmasi.'],
        ['key' => 'pickup', 'title' => 'Ambil di toko atau dikirim', 'text' => 'Ambil sendiri tanpa ongkos kirim, atau minta dikirim ke alamatmu. Ada gratis ongkir untuk belanja besar.'],
        ['key' => 'safe', 'title' => 'Stok dipesankan saat checkout', 'text' => 'Barang di pesananmu langsung disisihkan, jadi tidak diambil pembeli lain sebelum kamu bayar.'],
        ['key' => 'help', 'title' => 'Dilayani orang sungguhan', 'text' => 'Ada pertanyaan atau kendala? Kamu bicara dengan tim toko lewat WhatsApp, bukan dengan mesin penjawab.'],
    ],

    'faq' => [
        ['q' => 'Apakah stok di web sama dengan di toko?', 'a' => 'Ya. Web, kasir di toko, dan aplikasi Android memakai satu database stok. Begitu barang terjual di kasir, stok di web ikut berkurang.'],
        ['q' => 'Bagaimana cara membayar?', 'a' => 'Pilih transfer bank, QRIS, atau bayar di tempat saat checkout. Pesanan transfer diproses setelah pembayaran kami konfirmasi.'],
        ['q' => 'Berapa lama pesanan diproses?', 'a' => 'Pesanan yang sudah dibayar diproses di hari kerja yang sama bila masuk sebelum jam tutup toko.'],
        ['q' => 'Bisa ambil sendiri di toko?', 'a' => 'Bisa. Pilih "Ambil di toko" saat checkout dan tidak ada ongkos kirim.'],
    ],
];
