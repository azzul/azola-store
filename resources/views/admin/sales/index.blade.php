@extends('layouts.admin')
@section('title', $title)
@section('heading', $title)
@section('actions')@unless ($cancelled)<a class="btn" href="{{ route('admin.sales.create') }}">Input penjualan</a>@endunless @endsection
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nomor / pelanggan"></div>
    @if ($route !== 'admin.sales.pos')
    <div><label for="channel">Kanal</label><select id="channel" name="channel"><option value="">Semua</option>@foreach (['web' => 'Web', 'pos_desktop' => 'Pos Desktop', 'pos_android' => 'Pos Android', 'admin' => 'Admin'] as $k => $v)<option value="{{ $k }}" @selected(request('channel') === $k)>{{ $v }}</option>@endforeach</select></div>
    @endif
    <div><label for="method">Metode</label><select id="method" name="method"><option value="">Semua</option>@foreach (['cash', 'transfer', 'qris', 'debit', 'cod'] as $m)<option value="{{ $m }}" @selected(request('method') === $m)>{{ $m }}</option>@endforeach</select></div>
    @unless ($cancelled)<div><label for="bayar">Pembayaran</label><select id="bayar" name="bayar"><option value="">Semua</option><option value="unpaid" @selected(request('bayar') === 'unpaid')>Belum lunas</option></select></div>@endunless
</x-admin.period>
<div class="kpis">
    <div class="kpi"><b>{{ $sum->n }}</b><span>Transaksi</span></div>
    <div class="kpi"><b>@rp($sum->total)</b><span>Total penjualan</span></div>
    @unless ($cancelled)<div class="kpi"><b>@rp($sum->total - $sum->ret - $sum->cogs)</b><span>Laba kotor (setelah retur)</span></div>@endunless
</div>
<section class="card">
    @include('admin.orders.table', ['orders' => $orders])
    {{ $orders->links('pagination.store') }}
</section>
@endsection
