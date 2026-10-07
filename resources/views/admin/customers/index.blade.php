@extends('layouts.admin')
@section('title', 'Customer')
@section('heading', 'Customer')
@section('actions')<a class="btn" href="{{ route('admin.customers.create') }}">Tambah customer</a>@endsection
@section('content')
<div class="help">Customer otomatis tercatat saat ada pesanan dengan nomor telepon (web, kasir, atau input penjualan). Pilih level harga di data customer untuk memakai harga grosir/reseller.</div>
<form class="filters" method="get">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama, telepon, atau email"></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="">Aktif & nonaktif</option><option value="off" @selected(request('status') === 'off')>Nonaktif saja</option></select></div>
    <button class="btn btn--ghost">Terapkan</button>
</form>
<section class="card scroll">
    <table>
        <thead><tr><th>Customer</th><th>Telepon</th><th>Level harga</th><th class="num">Piutang</th><th class="num">Saldo DP</th></tr></thead>
        <tbody>
        @forelse ($customers as $c)
            <tr>
                <td><a href="{{ route('admin.customers.show', $c) }}">{{ $c->name }}</a>@if ($c->user_id) <span class="badge">akun web</span>@endif @unless ($c->is_active)<span class="badge badge--bad">nonaktif</span>@endunless</td>
                <td>{{ $c->phone ?: '-' }}</td>
                <td>{{ $c->priceLevel?->name ?? 'Ecer' }}</td>
                <td class="num">@rp($c->receivable())</td>
                <td class="num">@rp($c->depositBalance())</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada customer.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $customers->links('pagination.store') }}
</section>
@endsection
