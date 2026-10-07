@extends('layouts.admin')
@section('title', 'Mutasi stok')
@section('heading', 'Mutasi stok')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama / SKU"></div>
    <div><label for="warehouse">Gudang</label><select id="warehouse" name="warehouse"><option value="">Gudang jual (toko)</option>@foreach ($warehouses->where('is_main', false) as $w)<option value="{{ $w->id }}" @selected($warehouse === $w->id)>{{ $w->name }}</option>@endforeach</select></div>
</x-admin.period>
<section class="card scroll"><table>
    <thead><tr><th>Barang</th><th class="num">Stok awal</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Stok akhir</th><th class="num">Nilai akhir</th><th></th></tr></thead><tbody>
    @forelse ($rows as $r)
        <tr><td>{{ $r['product']->name }}<div class="muted">{{ $r['product']->sku }} · {{ $r['product']->unit }}</div></td>
        <td class="num">@qty($r['opening'] / 1000)</td><td class="num">@qty($r['in'] / 1000)</td><td class="num">@qty($r['out'] / 1000)</td><td class="num"><b>@qty($r['closing'] / 1000)</b></td><td class="num">@rp($r['value'])</td>
        <td class="no-print"><a href="{{ route('admin.stock.card', ['product' => $r['product']->id, 'from' => $from, 'to' => $to, 'warehouse' => $warehouse]) }}">Kartu stok</a></td></tr>
    @empty<tr><td colspan="7" class="muted">Tidak ada mutasi stok pada periode ini.</td></tr>@endforelse
</tbody><tfoot><tr><td colspan="5">Total nilai persediaan akhir</td><td class="num">@rp($rows->sum('value'))</td><td></td></tr></tfoot></table></section>
@endsection
