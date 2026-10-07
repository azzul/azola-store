@extends('layouts.store')
@section('content')
<div class="wrap page page--narrow">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Akun saya', route('account.home')], ['Pesanan saya', route('account.orders')]]])
    <header class="page__head"><h1>Pesanan saya</h1><p class="page__lead">Pesanan yang dibuat saat kamu masuk ke akun.</p></header>
    <div class="orderlist">@include('store.account.partials.orders', ['orders' => $orders])</div>
    {{ $orders->links('pagination.store') }}
    <p class="muted">Pesanan yang dibuat tanpa masuk tidak muncul di sini, tapi tetap bisa dibuka lewat tautan di halaman konfirmasinya.</p>
</div>
@endsection
