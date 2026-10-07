# Azola Store

Template toko online Laravel yang satu database dan satu API dengan **Azola Pos** (desktop dan Android).
Stok, penjualan, dan jurnal selalu sama di web, desktop, dan Android.

## Isi

- **Toko online** (publik): beranda, katalog, cari, halaman produk, keranjang, checkout, status pesanan. SEO lengkap (title/meta/canonical, JSON-LD Product/Store/FAQ/Breadcrumb, sitemap.xml, robots.txt). Stok di halaman ikut berubah otomatis.
- **Beranda 3D**: hero dengan tumpukan kardus yang terbuka dan berputar saat digulir (kartu produk melayang memakai data dan stok sungguhan), Kenapa kami, kategori, produk pilihan, cara belanja (kartu bertumpuk 3D), diagram "satu stok", ulasan, klien, FAQ, dan ajakan mampir. Gerak murni CSS + satu berkas JS kecil (`public/css/home.css`, `public/js/home.js`), mati otomatis bila pengunjung memilih "kurangi gerakan". Isi "Kenapa kami" diatur di `config/store.php` (`why`). Font Bricolage Grotesque dan Figtree dibawa sendiri di `public/fonts` (lisensi OFL).
- **Header dan navigasi**: logo, ikon Pesanan, Akun (masuk/daftar/keluar), dan Keranjang dengan lencana jumlah. Di HP ada bottom navbar (Beranda, Produk, Chat WhatsApp, Keranjang, Akun); di PC ada tombol melayang "Hubungi kami" (WhatsApp) di pojok kanan bawah. Pelanggan punya akun sendiri (`/masuk`, `/daftar`, `/akun`): riwayat pesanan, profil dan alamat tersimpan, dan checkout terisi otomatis. Akun pelanggan tidak bisa membuka admin atau API kasir.
- **Katalog bertingkat**: Kategori, Etalase ("Terlaris", "Promo", dst.), Produk, lalu Variasi dengan SKU, harga, dan stok sendiri (misalnya Berat x Gilingan). Halaman detail punya galeri foto (geser, miniatur, perbesar), pemilih variasi yang ikut mengganti harga, SKU, dan stok, tabel semua variasi, spesifikasi, dan tombol beli tetap di HP. Produk tanpa variasi tetap bekerja: dibuatkan produk 1:1 otomatis. Foto yang diunggah dibuat tiga ukuran otomatis (kartu persegi 640 px, besar 1400 px, miniatur) dengan GD, tanpa paket tambahan.
- **Pricelist** (`/pricelist`): daftar harga per kategori, variasi, SKU, satuan, dan status stok, bisa disaring, plus **unduh PDF** (`/pricelist.pdf`) bergaya sama dan banyak halaman. PDF dibuat sendiri (`app/Support/SimplePdf.php`), tanpa paket.
- **Artikel** (`/artikel`, `/artikel/{judul}`): blog dengan sampul, topik, waktu baca, data terstruktur Article, artikel terkait, dan masuk sitemap. Admin menulis dengan Markdown, bisa draf atau dijadwalkan.
- **Halaman profil**: Tentang kami (`/tentang-kami`), Kontak (`/kontak`, formulir masuk ke admin), Ulasan (`/ulasan`), Klien (`/klien`), Pertanyaan umum, Kebijakan privasi, Syarat, Pengembalian. Semua masuk sitemap dan punya data terstruktur.
- **Dashboard admin** (`/admin`): ringkasan penjualan, stok realtime, pesanan (catat bayar, selesai, batal), katalog toko (produk, variasi/SKU, galeri foto, etalase), SKU dan stok (stok masuk, penyesuaian, opname), kategori, artikel, jurnal, laporan, rekonsiliasi, token perangkat POS, pesan masuk, moderasi ulasan, daftar klien.
- **API Azola Pos** (`/api/v1`): login perangkat, sinkron produk/stok, kirim penjualan (aman diulang), laporan admin.

## Jalankan (di rumah atau di kantor)

```bash
git pull
php artisan migrate --seed     # buat tabel + data contoh (produk, variasi, foto, etalase, artikel) + akun admin
php artisan storage:link       # sekali saja, supaya foto produk tampil
php artisan serve
```

Buka http://127.0.0.1:8000 untuk toko dan http://127.0.0.1:8000/admin untuk admin.
Email admin ada di `.env` (`SEED_ADMIN_EMAIL`), password di `SEED_ADMIN_PASSWORD`. Ganti keduanya sebelum dipakai di server.

Bawaan memakai SQLite (`database/database.sqlite`). Untuk MySQL XAMPP, ubah `DB_*` di `.env`, buat database kosong, lalu jalankan `php artisan migrate --seed` lagi.

Tes: `php artisan test`

## Atur untuk klien baru

### Pengaturan toko (di database)

Nama, tagline, alamat, peta, jam buka, WhatsApp, email, sosmed, rekening, ongkir, warna, dan logo bisa diubah di admin: **/admin/pengaturan**. Nilainya disimpan di tabel `settings` dan menimpa `.env`/`config/store.php`; kolom yang dikosongkan kembali memakai nilai `.env`. Data dummy awal diisi oleh `php artisan db:seed --class=DemoSettingSeeder` (aman dijalankan ulang, tidak menimpa hasil edit admin).

Semua lewat `.env` dan `config/store.php`: nama toko (`APP_NAME`), WhatsApp, alamat, rekening, warna (`STORE_BRAND_COLOR`, `STORE_ACCENT_COLOR`), pajak, ongkir, metode bayar, FAQ. Logo: taruh berkas di `public/` lalu isi `STORE_LOGO`; kosong = logo bawaan. Alamat, sosmed, dan email di `.env` sekarang berisi data dummy, ganti sebelum dipakai klien. Hapus data contoh (`DemoStoreSeeder`, `DemoArticleSeeder`) sebelum dipakai klien. Foto contoh dibuat oleh `database/seeders/demo-images/_generate.py` (ilustrasi, ganti dengan foto asli toko).

## Isi halaman profil

- **Cerita, nilai, jam buka, sosmed, FAQ**: edit `config/store.php` dan `.env` (`STORE_SINCE`, `STORE_INSTAGRAM`, `STORE_TIKTOK`, `STORE_FACEBOOK`, `STORE_MAP_URL`).
- **Ulasan**: pembeli menulis di `/ulasan`, masuk antrean, tampil setelah disetujui di admin (menu Ulasan). Tidak ada ulasan contoh. Admin hanya boleh mencatat ulasan yang benar-benar diterima.
- **Klien**: tambah dari admin (menu Klien, logo opsional). Tampilkan hanya klien yang setuju namanya dipasang.
- **Kebijakan privasi, syarat, pengembalian**: teks template umum di `resources/views/store/pages/`. Pemilik toko perlu memeriksa dan menyesuaikannya.
- Halaman Ulasan dan Klien otomatis `noindex` selama masih kosong.

## Kasir desktop

Folder `pos-desktop/` berisi kasir ringan (tampilan web + cangkang C# WebView2, tanpa Electron). Lihat `pos-desktop/README.md`.

## Menghubungkan Azola Pos

1. Admin, menu **Perangkat POS**, buat token (pilih Desktop atau Android). Token tampil sekali.
2. Perangkat memanggil API dengan header `Authorization: Bearer azp_...` (atau login lewat `POST /api/v1/login` dengan email, password, `device_name`, `device_type`).

| Fungsi | Endpoint |
|---|---|
| Pengaturan toko (nama, alamat, pajak) | `GET /api/v1/settings` |
| Kategori, produk (`updated_since`) | `GET /api/v1/categories`, `GET /api/v1/products` |
| Stok berubah sejak kursor | `GET /api/v1/sync/stock?since=ID` |
| Kirim penjualan | `POST /api/v1/orders` (wajib `uuid` dari perangkat) |
| Bayar / batal | `POST /api/v1/orders/{uuid}/payments`, `.../cancel` (batal khusus admin) |
| Stok masuk, penyesuaian, opname | `POST /api/v1/stock/receive`, `/adjust`, `/opname` (admin) |
| Akun, jurnal, rekonsiliasi | `GET /api/v1/accounts`, `/journals`, `/reconciliation` (admin) |

Aturan penting:
- `uuid` dibuat perangkat saat transaksi. Kirim ulang uuid yang sama tidak membuat pesanan kedua, jadi aman saat koneksi putus.
- Harga dan HPP dihitung server. Stok tidak boleh minus: kalau kurang, seluruh transaksi ditolak.
- Uang = rupiah bulat. Jumlah barang boleh desimal (3 digit), dikirim sebagai teks.
- Poll stok tiap beberapa detik dengan `since` terakhir. Perubahan terlihat di semua perangkat.

## Cara data dijaga akurat

- Stok hanya berubah lewat `StockService` (dikunci per produk, riwayat mutasi tidak bisa diubah).
- Jurnal dibuat dalam transaksi yang sama dengan penjualan/stok, selalu seimbang, tidak bisa diubah/dihapus. Koreksi = jurnal balik (batal pesanan).
- Halaman **Rekonsiliasi** memeriksa: stok vs mutasi, jurnal seimbang, akun persediaan vs nilai stok, akun penjualan vs pesanan, piutang vs tagihan, pesanan punya jurnal, hitungan pesanan.

## Catatan teknis

- Realtime memakai polling berkursor (ringan, jalan di hosting biasa). Jalur ke WebSocket (Laravel Reverb) sudah disiapkan lewat event `StockChanged`.
- Token perangkat memakai tabel `api_tokens` (hash SHA-256), bukan Sanctum.
- Laravel Boost opsional: `composer require laravel/boost --dev` lalu `php artisan boost:install`.
