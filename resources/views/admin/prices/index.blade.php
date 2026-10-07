@extends('layouts.admin')
@section('title', 'Harga')
@section('heading', 'Harga')
@section('content')
<div class="help">Harga <strong>Ecer</strong> adalah harga dasar toko, kasir, dan web. Level lain (Grosir, Reseller, ...) dipilih per customer; kolom kosong berarti memakai harga Ecer. HPP dihitung otomatis dari pembelian, jadi tidak diisi manual.</div>
<div class="grid grid--2">
    <section class="card">
        <h2>Level harga</h2>
        <div class="pill-row">
            <span class="badge badge--ok">Ecer (dasar)</span>
            @foreach ($levels as $l)
                <form method="post" action="{{ route('admin.price-levels.destroy', $l) }}" class="inline-form" onsubmit="return confirm('Hapus level {{ $l->name }}? Harganya ikut terhapus.')">@csrf @method('DELETE')
                    <span class="badge">{{ $l->name }}</span><button class="btn btn--ghost btn--sm" aria-label="Hapus level {{ $l->name }}">×</button>
                </form>
            @endforeach
        </div>
    </section>
    <section class="card">
        <h2>Tambah level</h2>
        <form method="post" action="{{ route('admin.price-levels.store') }}" class="inline-form">@csrf
            <input name="name" placeholder="mis. Grosir" maxlength="60" required aria-label="Nama level"><button class="btn">Tambah</button>
        </form>
    </section>
</div>
<form class="filters" method="get">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama atau SKU"></div>
    <div><label for="category">Kategori</label><select id="category" name="category"><option value="">Semua</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <button class="btn btn--ghost">Terapkan</button>
</form>
<form method="post" action="{{ route('admin.prices.update') }}">@csrf @method('PUT')
<section class="card scroll">
    <table>
        <thead><tr><th>Produk</th><th class="num">HPP</th><th>Ecer (dasar)</th>@foreach ($levels as $l)<th>{{ $l->name }}</th>@endforeach<th class="num">Margin Ecer</th></tr></thead>
        <tbody>
        @forelse ($products as $p)
            <tr>
                <td>{{ $p->name }}{{ $p->variant_name ? ' - '.$p->variant_name : '' }}<div class="muted">{{ $p->sku }} · {{ $p->unit }}</div></td>
                <td class="num">@rp($p->cost)</td>
                <td><input type="number" min="0" name="base[{{ $p->id }}]" value="{{ $p->price }}" aria-label="Harga ecer {{ $p->name }}" style="width:8rem"></td>
                @foreach ($levels as $l)
                    <td><input type="number" min="0" name="level[{{ $p->id }}][{{ $l->id }}]" value="{{ $p->levelPrices->firstWhere('price_level_id', $l->id)?->price }}" placeholder="{{ $p->price }}" aria-label="Harga {{ $l->name }} {{ $p->name }}" style="width:8rem"></td>
                @endforeach
                <td class="num {{ $p->price - $p->cost < 0 ? 'neg' : '' }}">{{ $p->price > 0 ? round(($p->price - $p->cost) / $p->price * 100).'%' : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 4 + $levels->count() }}" class="muted">Tidak ada produk.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
<div class="actions"><button class="btn">Simpan harga halaman ini</button></div>
</form>
{{ $products->links('pagination.store') }}
@endsection
