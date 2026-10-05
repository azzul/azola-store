@extends('layouts.store')
@section('content')
<div class="wrap page prose prose--page">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Kebijakan privasi', route('privacy')]]])
    <h1>Kebijakan privasi</h1>
    <p>Halaman ini menjelaskan data apa yang {{ config('store.name') }} simpan saat kamu memakai website ini.</p>
    <h2>Data yang kami simpan</h2>
    <ul>
        <li>Saat checkout: nama, nomor telepon, email (bila diisi), alamat pengiriman, dan isi pesanan.</li>
        <li>Saat memakai formulir kontak atau ulasan: nama, nomor telepon/email, dan isi pesan.</li>
        <li>Cookie sesi, supaya keranjang belanjamu tidak hilang saat pindah halaman.</li>
    </ul>
    <h2>Untuk apa</h2>
    <p>Data dipakai untuk memproses dan mengantar pesanan, menghubungimu soal pesanan, membalas pertanyaan, dan menyimpan catatan penjualan sesuai keperluan pembukuan toko. Kami tidak menjual data pelanggan.</p>
    <h2>Siapa yang bisa melihat</h2>
    <p>Hanya tim toko yang berwenang. Jasa pengiriman menerima nama, telepon, dan alamat sebatas yang perlu untuk mengantar barang.</p>
    <h2>Hak kamu</h2>
    <p>Kamu bisa meminta salinan, perbaikan, atau penghapusan datamu dengan menghubungi kami lewat halaman <a href="{{ route('contact') }}">Kontak</a>. Data yang wajib kami simpan untuk pembukuan tidak bisa dihapus sebelum masa simpannya habis.</p>
    <p class="muted">Teks ini template umum. Pemilik toko perlu memeriksa dan menyesuaikannya dengan kondisi usahanya.</p>
</div>
@endsection
