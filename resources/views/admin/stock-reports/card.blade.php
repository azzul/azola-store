@extends('layouts.admin')
@section('title', 'Kartu stok')
@section('heading', 'Kartu stok')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="(bool) $product">
    <div><label for="product">Barang</label><select id="product" name="product" required><option value="">Pilih barang</option>@foreach ($products as $p)<option value="{{ $p->id }}" @selected($product?->id === $p->id)>{{ $p->name }}{{ $p->variant_name ? ' - '.$p->variant_name : '' }} ({{ $p->sku }})</option>@endforeach</select></div>
    <div><label for="warehouse">Gudang</label><select id="warehouse" name="warehouse"><option value="">Gudang jual (toko)</option>@foreach ($warehouses->where('is_main', false) as $w)<option value="{{ $w->id }}" @selected($warehouse === $w->id)>{{ $w->name }}</option>@endforeach</select></div>
</x-admin.period>
@if ($card)
<section class="card scroll">
    <h2 class="report-title">{{ $product->name }} <span class="muted">{{ $product->sku }} · {{ $product->unit }}</span></h2>
    <table><thead><tr><th>Waktu</th><th>Jenis</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo</th></tr></thead><tbody>
        <tr class="sub"><td colspan="5">Saldo awal</td><td class="num">@qty($card['opening'] / 1000)</td></tr>
        @forelse ($card['rows'] as $r)<tr><td>{{ \Carbon\Carbon::parse($r['m']->created_at)->format('d/m/Y H:i') }}</td><td>{{ $r['m']->type }}</td><td>{{ $r['m']->note }}{{ $r['m']->warehouse ? ' · '.$r['m']->warehouse : '' }}</td><td class="num">{{ $r['in'] ? \App\Support\Qty::pretty($r['in'] / 1000) : '' }}</td><td class="num">{{ $r['out'] ? \App\Support\Qty::pretty($r['out'] / 1000) : '' }}</td><td class="num">@qty($r['balance'] / 1000)</td></tr>
        @empty<tr><td colspan="6" class="muted">Tidak ada pergerakan stok pada periode ini.</td></tr>@endforelse
    </tbody><tfoot><tr><td colspan="5">Saldo akhir</td><td class="num">@qty($card['closing'] / 1000)</td></tr></tfoot></table>
</section>
@else<p class="muted">Pilih barang untuk melihat kartu stoknya.</p>@endif
@endsection
