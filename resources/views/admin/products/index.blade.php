@extends('layouts.admin')
@section('title', 'Produk')
@section('heading', 'Produk')
@section('actions')<a class="btn" href="{{ route('admin.products.create') }}">Tambah produk</a>@endsection
@section('content')
<form class="filters" method="get">
    <div><label for="q">Cari produk</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama, SKU, atau barcode"></div>
    <button class="btn btn--ghost">Cari</button>
</form>
<section class="card scroll">
<table>
    <thead><tr><th></th><th>Produk</th><th>Kategori</th><th class="num">Harga</th><th class="num">Stok</th><th>Tampil</th></tr></thead>
    <tbody>
    @forelse ($products as $p)
        <tr>
            <td>@if ($p->imageUrl())<img class="thumb" src="{{ $p->imageUrl() }}" alt="" loading="lazy">@else<span class="thumb">{{ $p->initials() }}</span>@endif</td>
            <td><a href="{{ route('admin.products.edit', $p) }}">{{ $p->name }}</a><div class="muted">{{ $p->sku }}</div></td>
            <td>{{ $p->category?->name ?? '-' }}</td>
            <td class="num">{{ \App\Support\Rupiah::format($p->price) }}</td>
            <td class="num" data-state="{{ $p->stockState() }}"><span class="badge">{{ \App\Support\Qty::pretty($p->stock_qty) }} {{ $p->unit }}</span></td>
            <td>{{ $p->is_active ? ($p->is_online ? 'Kasir + web' : 'Kasir saja') : 'Nonaktif' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Belum ada produk. <a href="{{ route('admin.products.create') }}">Tambah produk pertama</a>.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $products->links('pagination.store') }}
</section>
@endsection
