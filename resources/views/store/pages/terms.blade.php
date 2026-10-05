@extends('layouts.store')
@section('content')
<div class="wrap page prose prose--page">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Syarat dan ketentuan', route('terms')]]])
    <h1>Syarat dan ketentuan</h1>
    <h2>Harga dan stok</h2>
    <p>Harga dan stok di website mengikuti sistem kasir toko dan bisa berubah. Stok dipesankan untukmu saat pesanan dibuat. Bila ternyata ada selisih yang tidak bisa dipenuhi, kami menghubungimu untuk mengganti atau membatalkan, dan pembayaran yang sudah masuk dikembalikan penuh.</p>
    <h2>Pembayaran</h2>
    <p>Pembayaran lewat {{ implode(', ', array_values(config('store.web_payment_methods'))) }}. Pesanan transfer dan QRIS diproses setelah pembayaran kami konfirmasi.</p>
    <h2>Pengiriman dan pengambilan</h2>
    <p>Ongkos kirim ditampilkan sebelum kamu memesan{!! config('store.shipping.free_over') > 0 ? ', dan gratis untuk belanja di atas '.e(\App\Support\Rupiah::format(config('store.shipping.free_over'))) : '' !!}. Barang juga bisa diambil di toko tanpa ongkos kirim.</p>
    <h2>Pembatalan dan pengembalian</h2>
    <p>Lihat halaman <a href="{{ route('returns') }}">Pengembalian dan pembatalan</a>.</p>
    <h2>Perubahan</h2>
    <p>Ketentuan ini bisa diperbarui sewaktu-waktu. Yang berlaku adalah versi yang tampil saat kamu memesan.</p>
    <p class="muted">Teks ini template umum. Pemilik toko perlu memeriksa dan menyesuaikannya.</p>
</div>
@endsection
