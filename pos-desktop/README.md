# Azola Pos Desktop

Kasir desktop ringan untuk Azola Store. Tampilan kasir adalah web (`ui/`), dibungkus cangkang C# kecil (`app/`) yang memakai Microsoft Edge WebView2. Tidak ada Electron: hasil build hanya beberapa MB.

## Isi

- `ui/` : tampilan kasir (HTML, CSS, JS tanpa library). Mengutamakan keyboard (kasir hampir tidak perlu mouse), tetap responsif: dua kolom di layar lebar, satu kolom di layar sempit.
- `app/` : cangkang Windows (.NET Framework 4.8 + WebView2). Tugasnya hanya menampilkan `ui/`, mencetak struk ESC/POS langsung ke printer, dan membuka laci kasir.

## Fitur

- Login dengan akun dari dashboard toko. Perangkat tercatat sebagai Pos Desktop.
- Kolom scan selalu aktif. Ketik di mana saja langsung masuk ke kolom scan, sehingga scanner barcode (yang mengetik seperti keyboard) selalu bekerja.
- Tabel keranjang padat, bisa dinavigasi dengan panah. Enter pada kolom kosong membuka pembayaran.
- Tahan dan panggil transaksi (beberapa pelanggan bergantian), diskon per baris atau per transaksi (Rp atau %).
- Tunai, transfer, QRIS, debit. Uang pas dan tombol cepat, hitung kembalian. Bayar kurang dicatat sebagai piutang.
- Struk 58/80 mm, cetak ulang dari Riwayat.
- **Offline**: produk disimpan di perangkat. Transaksi masuk antrean dengan `uuid`, dikirim otomatis saat internet kembali. Server menolak uuid ganda, jadi aman dikirim ulang.
- Stok mengikuti server tiap 4 detik, dikurangi barang di antrean yang belum terkirim. Penjualan dari web atau Android ikut terlihat.
- Transaksi yang ditolak server (misal stok kurang karena terjual di tempat lain) tetap di Antrean dengan alasannya, tidak hilang diam-diam.

## Pintasan keyboard

| Tombol | Fungsi |
|---|---|
| ketik kode + Enter | tambah 1. Contoh `SMB-001` |
| `3*kode` atau `3xkode` | tambah 3 (desimal boleh: `0,5*kode`) |
| ketik nama + panah + Enter | pilih dari saran |
| Enter (kolom kosong) | bayar |
| panah atas/bawah, PgUp/PgDn | pilih baris |
| `+` / `-` | ubah jumlah baris terpilih |
| Del / Ctrl+Del | hapus baris / kosongkan transaksi |
| F1 | bantuan |
| F2 | fokus ke kolom scan |
| F3 | diskon transaksi (`5000`, `5k`, `10%`) |
| F4 | ubah jumlah (0 = hapus) |
| F5 | diskon baris |
| F6 / F7 | tahan / panggil transaksi |
| F8 / F10 | struk terakhir / riwayat |
| F9 | bayar |
| Di jendela bayar: F1-F4 | Tunai, Transfer, QRIS, Debit |
| Di jendela bayar: F5-F8 | uang pas dan nominal cepat |
| Di jendela bayar: Enter / Esc | simpan / batal |
| Di struk: P / Enter | cetak / selesai |

Nominal bisa ditulis `100k` atau `100rb`.

## Mencoba tampilan tanpa Windows

```bash
cd pos-desktop/ui
python3 -m http.server 8100
```

Buka http://127.0.0.1:8100, isi alamat server Azola Store (mis. http://127.0.0.1:8000). Cetak memakai dialog cetak browser.

## Membangun aplikasi Windows

Perlu Visual Studio 2022 (beban kerja ".NET desktop") atau .NET SDK 8.

```powershell
cd pos-desktop\app
dotnet build -c Release
# hasil: app\bin\Release\net48\AzolaPos.exe + folder ui\
```

Salin seluruh isi `bin\Release\net48` ke komputer kasir. Syarat komputer kasir: Windows 10 atau 11. Windows 7/8 tidak didukung WebView2.
Kalau komputer kasir belum punya WebView2 Runtime, aplikasi akan memberi tahu. Pemasangnya gratis dari Microsoft (sekitar 2 MB untuk bootstrapper).

Status: kode C# ini belum pernah dikompilasi atau dijalankan di Windows (dibuat di lingkungan tanpa .NET). Tampilan web sudah diuji, termasuk alur offline. Kalau build gagal, kirim pesan errornya.

## Printer

Di Pengaturan isi nama printer persis seperti di Windows (Devices and Printers), atau kosongkan untuk printer bawaan. Printer thermal ESC/POS (USB, atau Bluetooth yang terpasang sebagai printer Windows) bekerja lewat mode RAW. Laci kasir yang tersambung ke printer (RJ11) dibuka lewat perintah ESC p.
