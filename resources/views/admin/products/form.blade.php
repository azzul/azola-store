@extends('layouts.admin')
@php $isNew = ! $product->exists; @endphp
@section('title', $isNew ? 'Produk baru' : $product->name)
@section('heading', $isNew ? 'Produk baru' : $product->name)
@section('actions')
    <a class="btn btn--ghost" href="{{ route('admin.products.index') }}">Kembali</a>
    @unless ($isNew)<a class="btn btn--ghost" href="{{ $product->url() }}" target="_blank" rel="noopener">Lihat di toko</a>@endunless
@endsection
@section('content')
<form class="card" method="post" enctype="multipart/form-data" action="{{ $isNew ? route('admin.products.store') : route('admin.products.update', $product) }}">
    @csrf
    @unless ($isNew) @method('PUT') @endunless
    <h2>Data produk</h2>
    @if ($product->exists && $product->group && ! $product->group->auto)
        <p class="hint" style="margin-top:-.4rem">Variasi dari produk <a href="{{ route('admin.catalog.edit', $product->group) }}">{{ $product->group->name }}</a>. Atur foto, etalase, dan variasi lain di sana.</p>
    @endif
    <div class="form-grid">
        <div class="field"><label for="name">Nama</label><input id="name" type="text" name="name" value="{{ old('name', $product->name) }}" required maxlength="160"></div>
        <div class="field"><label for="sku">SKU</label><input id="sku" type="text" name="sku" value="{{ old('sku', $product->sku) }}" required maxlength="60"></div>
        @if ($product->exists && $product->group && ! $product->group->auto)<div class="field"><label for="variant_name">Nama variasi</label><input id="variant_name" type="text" name="variant_name" value="{{ old('variant_name', $product->variant_name) }}" maxlength="80"></div>@endif
        <div class="field"><label for="barcode">Barcode</label><input id="barcode" type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}" maxlength="60"></div>
        <div class="field"><label for="category_id">Kategori</label>
            <select id="category_id" name="category_id"><option value="">Tanpa kategori</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) old('category_id', $product->category_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label for="unit">Satuan</label><input id="unit" type="text" name="unit" value="{{ old('unit', $product->unit) }}" required maxlength="20"></div>
        <div class="field"><label for="price">Harga jual (Rp)</label><input id="price" type="number" name="price" min="0" value="{{ old('price', $product->price) }}" required></div>
        <div class="field"><label for="min_stock">Batas stok menipis</label><input id="min_stock" type="number" step="0.001" min="0" name="min_stock" value="{{ old('min_stock', \App\Support\Qty::pretty($product->min_stock ?? 0)) }}"><div class="hint">Kosong/0 = pakai batas bawaan toko.</div></div>
        <div class="field"><label for="image">Foto produk (maks. 5 MB)</label><input id="image" type="file" name="image" accept="image/*">@if ($product->imageUrl())<div class="hint">Sudah ada foto. Unggah lagi untuk mengganti.</div>@endif</div>
    </div>
    <div class="field"><label for="description">Deskripsi</label><textarea id="description" name="description" maxlength="5000">{{ old('description', $product->description) }}</textarea></div>
    <div class="form-grid">
        <div class="field"><label for="meta_title">Judul SEO</label><input id="meta_title" type="text" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" maxlength="70"><div class="hint">Kosong = pakai nama produk.</div></div>
        <div class="field"><label for="meta_description">Deskripsi SEO</label><input id="meta_description" type="text" name="meta_description" value="{{ old('meta_description', $product->meta_description) }}" maxlength="320"></div>
    </div>
    <div class="row" style="margin-bottom:1rem">
        <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> Aktif (muncul di kasir)</label>
        <label class="check"><input type="checkbox" name="is_online" value="1" @checked(old('is_online', $product->is_online))> Tampil di toko online</label>
        <label class="check"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))> Produk pilihan di beranda</label>
        @unless ($isNew)<label class="check"><input type="checkbox" name="regenerate_slug" value="1"> Perbarui alamat halaman sesuai nama baru</label>@endunless
    </div>

    @if ($isNew)
    <h2>Stok awal (opsional)</h2>
    <div class="form-grid">
        <div class="field"><label for="initial_qty">Jumlah</label><input id="initial_qty" type="number" step="0.001" min="0" name="initial_qty" value="{{ old('initial_qty') }}"></div>
        <div class="field"><label for="initial_cost">Harga beli / HPP per satuan (Rp)</label><input id="initial_cost" type="number" min="0" name="initial_cost" value="{{ old('initial_cost') }}"></div>
        <div class="field"><label for="funding">Sumber dana</label><select id="funding" name="funding">@foreach ($fundings as $key => $label)<option value="{{ $key }}" @selected($key === 'equity')>{{ ['cash' => 'Kas tunai', 'bank' => 'Bank / transfer', 'payable' => 'Utang ke pemasok', 'equity' => 'Modal pemilik (stok awal)'][$key] ?? $key }}</option>@endforeach</select></div>
    </div>
    @endif
    <button class="btn">{{ $isNew ? 'Simpan produk' : 'Simpan perubahan' }}</button>
</form>

@unless ($isNew)
<section class="card" data-state="{{ $product->stockState() }}">
    <h2>Stok sekarang: {{ \App\Support\Qty::pretty($product->stock_qty) }} {{ $product->unit }} <span class="badge">{{ ['ok' => 'Aman', 'low' => 'Menipis', 'out' => 'Habis'][$product->stockState()] }}</span></h2>
    <p class="muted">HPP rata-rata {{ \App\Support\Rupiah::format($product->cost) }} · nilai {{ \App\Support\Rupiah::format($product->inventoryValue()) }}. Stok hanya berubah lewat tiga form di bawah, supaya jurnal selalu ikut.</p>
</section>

<div class="grid grid--3">
    <form class="card" method="post" action="{{ route('admin.products.receive', $product) }}">
        @csrf
        <h2>Stok masuk</h2>
        <div class="field"><label for="r_qty">Jumlah</label><input id="r_qty" type="number" step="0.001" min="0.001" name="qty" required></div>
        <div class="field"><label for="r_cost">Harga beli per satuan (Rp)</label><input id="r_cost" type="number" min="0" name="unit_cost" value="{{ $product->cost }}" required></div>
        <div class="field"><label for="r_fund">Dibayar dari</label><select id="r_fund" name="funding">@foreach ($fundings as $key => $label)<option value="{{ $key }}" @selected($key === 'cash')>{{ ['cash' => 'Kas tunai', 'bank' => 'Bank / transfer', 'payable' => 'Utang ke pemasok', 'equity' => 'Modal pemilik (stok awal)'][$key] ?? $key }}</option>@endforeach</select></div>
        <div class="field"><label for="r_note">Catatan</label><input id="r_note" type="text" name="note" maxlength="200"></div>
        <button class="btn">Catat stok masuk</button>
    </form>
    <form class="card" method="post" action="{{ route('admin.products.adjust', $product) }}">
        @csrf
        <h2>Penyesuaian (rusak/hilang/ketemu)</h2>
        <div class="field"><label for="a_delta">Selisih (minus untuk mengurangi)</label><input id="a_delta" type="number" step="0.001" name="delta" required></div>
        <div class="field"><label for="a_note">Alasan</label><input id="a_note" type="text" name="note" maxlength="200" required></div>
        <button class="btn btn--ghost">Catat penyesuaian</button>
    </form>
    <form class="card" method="post" action="{{ route('admin.products.opname', $product) }}">
        @csrf
        <h2>Opname (hitung fisik)</h2>
        <div class="field"><label for="o_counted">Jumlah hasil hitung</label><input id="o_counted" type="number" step="0.001" min="0" name="counted" required></div>
        <div class="field"><label for="o_note">Catatan</label><input id="o_note" type="text" name="note" maxlength="200"></div>
        <button class="btn btn--ghost">Catat opname</button>
        <p class="hint">Selisih dengan stok sistem dicatat sebagai mutasi dan jurnal selisih persediaan.</p>
    </form>
</div>

<section class="card scroll">
    <h2>Riwayat stok terakhir</h2>
    <table>
        <thead><tr><th>Waktu</th><th>Jenis</th><th>Sumber</th><th class="num">Perubahan</th><th class="num">Sisa</th><th>Catatan</th></tr></thead>
        <tbody>
        @forelse ($movements as $m)
            <tr><td>{{ $m->created_at?->format('d/m/Y H:i') }}</td><td>{{ $m->type }}</td><td>{{ $m->source }}</td><td class="num">{{ $m->qty_change > 0 ? '+' : '' }}{{ \App\Support\Qty::pretty($m->qty_change) }}</td><td class="num">{{ \App\Support\Qty::pretty($m->qty_after) }}</td><td>{{ $m->note }}</td></tr>
        @empty
            <tr><td colspan="6" class="muted">Belum ada mutasi.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endunless
@endsection
