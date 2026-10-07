@extends('layouts.admin')
@section('title', 'Katalog toko')
@section('heading', 'Katalog toko')
@section('actions')<a class="btn" href="{{ route('admin.catalog.create') }}">Tambah produk</a>@endsection
@section('content')
<p class="muted">Produk yang tampil di toko online. Satu produk bisa punya banyak variasi, tiap variasi punya SKU, harga, dan stok sendiri.</p>
<form class="filters" method="get">
    <div><label for="q">Cari produk</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama, merek, SKU, atau barcode"></div>
    <button class="btn btn--ghost">Cari</button>
</form>
<section class="card scroll">
<table>
    <thead><tr><th></th><th>Produk</th><th>Kategori</th><th>Etalase</th><th class="num">Variasi</th><th class="num">Harga</th><th>Stok</th><th>Tampil</th></tr></thead>
    <tbody>
    @forelse ($groups as $g)
        @php $state = $g->variants->isEmpty() ? 'out' : $g->stockState(); @endphp
        <tr>
            <td>@if ($g->imageUrl('thumb'))<img class="thumb" src="{{ $g->imageUrl('thumb') }}" alt="" loading="lazy">@else<span class="thumb">{{ $g->initials() }}</span>@endif</td>
            <td><a href="{{ route('admin.catalog.edit', $g) }}">{{ $g->name }}</a>@if ($g->brand)<div class="muted">{{ $g->brand }}</div>@endif</td>
            <td>{{ $g->category?->name ?? '-' }}</td>
            <td>{{ $g->etalases->pluck('name')->implode(', ') ?: '-' }}</td>
            <td class="num">{{ $g->variants->count() }}</td>
            <td class="num">{{ \App\Support\Rupiah::format($g->price_min) }}@if ($g->hasRange())<div class="muted">s/d {{ \App\Support\Rupiah::format($g->price_max) }}</div>@endif</td>
            <td data-state="{{ $state }}"><span class="badge">{{ ['ok' => 'Tersedia', 'low' => 'Menipis', 'out' => 'Habis'][$state] }}</span></td>
            <td>{{ $g->is_active && $g->is_online ? 'Ya' : ($g->is_active ? 'Hanya kasir' : 'Nonaktif') }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">Belum ada produk. <a href="{{ route('admin.catalog.create') }}">Tambah produk pertama</a>.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $groups->links('pagination.store') }}
</section>
@endsection
