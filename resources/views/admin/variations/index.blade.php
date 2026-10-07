@extends('layouts.admin')
@section('title', 'Variasi')
@section('heading', 'Variasi')
@section('actions')<a class="btn" href="{{ route('admin.catalog.create') }}">Produk toko baru</a>@endsection
@section('content')
<div class="help">Variasi dibuat per produk toko (mis. Kaos: Ukuran S/M/L × Warna). Setiap variasi punya SKU, harga, stok, dan satuan sendiri. Tambah atau ubah variasi lewat <a href="{{ route('admin.catalog.index') }}">Katalog toko</a>.</div>
<section class="card scroll">
    <h2>Atribut yang dipakai</h2>
    <table>
        <thead><tr><th>Atribut</th><th>Nilai</th><th>Dipakai di produk</th></tr></thead>
        <tbody>
        @forelse ($attributes as $name => $info)
            <tr>
                <td><strong>{{ $name }}</strong></td>
                <td><div class="pill-row">@foreach ($info['values'] ?? [] as $value => $n)<span class="badge">{{ $value }} · {{ $n }} SKU</span>@endforeach</div></td>
                <td>{{ implode(', ', array_slice($info['groups'] ?? [], 0, 4)) }}{{ count($info['groups'] ?? []) > 4 ? ' dan '.(count($info['groups']) - 4).' lainnya' : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Belum ada atribut variasi.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
<form class="filters" method="get"><div><label for="q">Cari variasi</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama, SKU, atau pilihan"></div><button class="btn btn--ghost">Cari</button></form>
<section class="card scroll">
    <h2>Semua SKU variasi</h2>
    <table>
        <thead><tr><th>Produk toko</th><th>Variasi</th><th>SKU</th><th class="num">Harga</th><th class="num">Stok</th></tr></thead>
        <tbody>
        @forelse ($variants as $v)
            <tr>
                <td><a href="{{ route('admin.catalog.edit', $v->group_id) }}">{{ $v->group?->name }}</a></td>
                <td>{{ $v->variant_name ?: '-' }}@if ($v->options)<div class="muted">{{ collect($v->options)->map(fn ($val, $k) => "$k: $val")->implode(' · ') }}</div>@endif</td>
                <td><a href="{{ route('admin.products.edit', $v) }}">{{ $v->sku }}</a></td>
                <td class="num">@rp($v->price)</td>
                <td class="num" data-state="{{ $v->stockState() }}"><span class="badge">@qty($v->stock_qty) {{ $v->unit }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada produk dengan variasi.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $variants->links('pagination.store') }}
</section>
@endsection
