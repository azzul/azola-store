@extends('layouts.store')
@section('content')
<div class="wrap page prose prose--page">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Pengembalian dan pembatalan', route('returns')]]])
    <h1>Pengembalian dan pembatalan</h1>
    <h2>Membatalkan pesanan</h2>
    <p>Pesanan yang belum diproses bisa dibatalkan. Hubungi kami lewat <a href="{{ route('contact') }}">Kontak</a> dengan menyebut nomor pesanan. Stok dikembalikan dan pembayaran yang sudah masuk kami kembalikan.</p>
    <h2>Barang rusak atau salah kirim</h2>
    <p>Hubungi kami secepatnya setelah barang diterima, sertakan nomor pesanan dan foto barang. Kami akan mengganti atau mengembalikan dana sesuai kondisi.</p>
    <h2>Barang yang tidak bisa dikembalikan</h2>
    <p>Barang yang sudah dipakai, rusak karena pemakaian, atau barang yang mudah rusak/basi tidak bisa dikembalikan, kecuali memang cacat sejak diterima.</p>
    <p class="muted">Teks ini template umum. Sesuaikan dengan kebijakan toko sebelum dipakai.</p>
</div>
@endsection
