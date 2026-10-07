@extends('layouts.admin')
@section('title', 'Penjualan detail')
@section('heading', 'Penjualan detail (per item)')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Barang / SKU / nomor"></div>
    <div><label for="channel">Kanal</label><select id="channel" name="channel"><option value="">Semua</option>@foreach (['web' => 'Web', 'pos_desktop' => 'Pos Desktop', 'pos_android' => 'Pos Android', 'admin' => 'Admin'] as $k => $v)<option value="{{ $k }}" @selected(request('channel') === $k)>{{ $v }}</option>@endforeach</select></div>
</x-admin.period>
<div class="kpis">
    <div class="kpi"><b>@qty($sum['qty'] / 1000)</b><span>Barang terjual</span></div>
    <div class="kpi"><b>@rp($sum['revenue'])</b><span>Penjualan bersih item</span></div>
    <div class="kpi"><b>@rp($sum['revenue'] - $sum['cost'])</b><span>Laba kotor (HPP @rp($sum['cost']))</span></div>
</div>
<section class="card scroll">
<table>
    <thead><tr><th>Tanggal</th><th>Nomor</th><th>Barang</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Diskon</th><th class="num">Subtotal</th><th class="num">HPP</th><th class="num">Laba</th></tr></thead>
    <tbody>
    @forelse ($items as $i)
        @php($cost = \App\Support\Qty::value(\App\Support\Qty::toMilli($i->qty), (int) $i->unit_cost))
        <tr><td>{{ \Carbon\Carbon::parse($i->ordered_at)->format('d/m/Y H:i') }}</td><td><a href="{{ route('admin.orders.show', $i->oid) }}">{{ $i->order_number }}</a></td>
        <td>{{ $i->name }}<div class="muted">{{ $i->sku }}</div></td><td class="num">{{ \App\Support\Qty::pretty($i->qty) }}</td><td class="num">@rp($i->price)</td><td class="num">@rp($i->discount)</td><td class="num">@rp($i->line_total)</td><td class="num">@rp($cost)</td><td class="num">@rp($i->line_total - $cost)</td></tr>
    @empty<tr><td colspan="9" class="muted">Tidak ada penjualan pada periode ini.</td></tr>@endforelse
    </tbody>
</table>
{{ $items->links('pagination.store') }}
</section>
@endsection
