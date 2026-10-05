# Azola Store

Template toko online Laravel yang satu database dan satu API dengan **Azola Pos** (desktop dan Android).
Stok, penjualan, dan jurnal selalu sama di web, desktop, dan Android.

## Isi

- **Toko online** (publik): beranda, katalog, cari, halaman produk, keranjang, checkout, status pesanan. SEO lengkap (title/meta/canonical, JSON-LD Product/Store/FAQ/Breadcrumb, sitemap.xml, robots.txt). Stok di halaman ikut berubah otomatis.
- **Dashboard admin** (`/admin`): ringkasan penjualan, stok realtime, pesanan (catat bayar, selesai, batal), produk (foto, stok masuk, penyesuaian, opname), kategori, jurnal, laporan, rekonsiliasi, token perangkat POS.
- **API Azola Pos** (`/api/v1`): login perangkat, sinkron produk/stok, kirim penjualan (aman diulang), laporan admin.

## Jalankan (di rumah atau di kantor)

```bash
git pull
php artisan migrate --seed     # buat tabel + data contoh + akun admin
php artisan storage:link       # sekali saja, supaya foto produk tampil
php artisan serve
```

Buka http://127.0.0.1:8000 untuk toko dan http://127.0.0.1:8000/admin untuk admin.
Email admin ada di `.env` (`SEED_ADMIN_EMAIL`), password di `SEED_ADMIN_PASSWORD`. Ganti keduanya sebelum dipakai di server.

Bawaan memakai SQLite (`database/database.sqlite`). Untuk MySQL XAMPP, ubah `DB_*` di `.env`, buat database kosong, lalu jalankan `php artisan migrate --seed` lagi.

Tes: `php artisan test`

## Atur untuk klien baru

Semua lewat `.env` dan `config/store.php`: nama toko (`APP_NAME`), WhatsApp, alamat, rekening, warna (`STORE_BRAND_COLOR`, `STORE_ACCENT_COLOR`), pajak, ongkir, metode bayar, FAQ. Hapus data contoh (`DemoStoreSeeder`) sebelum dipakai klien.

## Menghubungkan Azola Pos

1. Admin, menu **Perangkat POS**, buat token (pilih Desktop atau Android). Token tampil sekali.
2. Perangkat memanggil API dengan header `Authorization: Bearer azp_...` (atau login lewat `POST /api/v1/login` dengan email, password, `device_name`, `device_type`).

| Fungsi | Endpoint |
|---|---|
| Kategori, produk (`updated_since`) | `GET /api/v1/categories`, `GET /api/v1/products` |
| Stok berubah sejak kursor | `GET /api/v1/sync/stock?since=ID` |
| Kirim penjualan | `POST /api/v1/orders` (wajib `uuid` dari perangkat) |
| Bayar / batal | `POST /api/v1/orders/{uuid}/pay`, `.../cancel` (batal khusus admin) |
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
