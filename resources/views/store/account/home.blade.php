@extends('layouts.store')
@section('content')
<div class="wrap page">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Akun saya', route('account.home')]]])
    <header class="page__head"><h1>Halo, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1><p class="page__lead">Kelola pesanan dan data pengirimanmu di sini.</p></header>

    <div class="acctgrid">
        <a class="acctcard" href="{{ route('account.orders') }}">
            <span class="acctcard__ico">@include('store.partials.icon', ['name' => 'receipt', 'size' => 26])</span>
            <strong>Pesanan saya</strong><span class="muted">{{ $orderCount }} pesanan</span>
        </a>
        <a class="acctcard" href="{{ route('account.profile') }}">
            <span class="acctcard__ico">@include('store.partials.icon', ['name' => 'user', 'size' => 26])</span>
            <strong>Profil dan alamat</strong><span class="muted">{{ $user->phone ?: 'Nomor belum diisi' }}</span>
        </a>
        <a class="acctcard" href="{{ route('cart.index') }}">
            <span class="acctcard__ico">@include('store.partials.icon', ['name' => 'cart', 'size' => 26])</span>
            <strong>Keranjang</strong><span class="muted">Lanjutkan belanja</span>
        </a>
    </div>

    <section class="block block--flush">
        <div class="block__head"><h2>Pesanan terakhir</h2><a href="{{ route('account.orders') }}">Lihat semua</a></div>
        @include('store.account.partials.orders', ['orders' => $recent])
    </section>

    <form method="post" action="{{ route('account.logout') }}" class="auth__alt">@csrf<button class="link link--plain" type="submit">Keluar dari akun</button></form>
</div>
@endsection
