@extends('layouts.admin')
@php $isNew = ! $group->exists; @endphp
@section('title', $isNew ? 'Produk baru' : $group->name)
@section('heading', $isNew ? 'Produk baru' : $group->name)
@section('actions')
    <a class="btn btn--ghost" href="{{ route('admin.catalog.index') }}">Kembali</a>
    @unless ($isNew)<a class="btn btn--ghost" href="{{ $group->url() }}" target="_blank" rel="noopener">Lihat di toko</a>@endunless
@endsection
@section('content')
@if ($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif

<form class="card" method="post" action="{{ $isNew ? route('admin.catalog.store') : route('admin.catalog.update', $group) }}">
    @csrf
    @unless ($isNew) @method('PUT') @endunless
    <h2>Tentang produk</h2>
    @if ($group->auto)<p class="hint" style="margin-top:-.4rem">Produk ini dibuat otomatis dari satu SKU. Setelah disimpan di sini, kamu bisa menambahkan variasi lain.</p>@endif
    <div class="form-grid">
        <div class="field"><label for="name">Nama produk</label><input id="name" type="text" name="name" value="{{ old('name', $group->name) }}" required maxlength="160"></div>
        <div class="field"><label for="brand">Merek</label><input id="brand" type="text" name="brand" value="{{ old('brand', $group->brand) }}" maxlength="80"></div>
        <div class="field"><label for="category_id">Kategori</label>
            <select id="category_id" name="category_id"><option value="">Tanpa kategori</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) old('category_id', $group->category_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label for="option_names">Nama pilihan variasi</label><input id="option_names" type="text" name="option_names" value="{{ old('option_names', implode(', ', (array) $group->option_names)) }}" maxlength="200" placeholder="Contoh: Ukuran, Warna"><div class="hint">Pisahkan dengan koma, maksimal 3. Kosongkan kalau produk tanpa pilihan.</div></div>
    </div>
    <div class="field"><label for="summary">Ringkasan (satu kalimat)</label><input id="summary" type="text" name="summary" value="{{ old('summary', $group->summary) }}" maxlength="240"></div>
    <div class="field"><label for="description">Deskripsi lengkap</label><textarea id="description" name="description" rows="6" maxlength="8000">{{ old('description', $group->description) }}</textarea><div class="hint">Baris kosong memisahkan paragraf.</div></div>
    <div class="form-grid">
        <div class="field"><label for="highlights">Poin keunggulan</label><textarea id="highlights" name="highlights" rows="4" maxlength="2000" placeholder="Satu poin per baris">{{ old('highlights', implode("\n", (array) $group->highlights)) }}</textarea></div>
        <div class="field"><label for="specs">Spesifikasi</label><textarea id="specs" name="specs" rows="4" maxlength="3000" placeholder="Satu per baris, format  Label: isi">{{ old('specs', collect((array) $group->specs)->map(fn ($s) => ($s['label'] ?? '').': '.($s['value'] ?? ''))->implode("\n")) }}</textarea></div>
    </div>

    <h2>Etalase</h2>
    @if ($etalases->isEmpty())
        <p class="muted">Belum ada etalase. <a href="{{ route('admin.etalases.index') }}">Buat etalase</a> dulu, misalnya "Terlaris" atau "Promo".</p>
    @else
        <div class="row" style="margin-bottom:1rem">
            @php $picked = old('etalases', $group->exists ? $group->etalases->pluck('id')->all() : []); @endphp
            @foreach ($etalases as $e)
                <label class="check"><input type="checkbox" name="etalases[]" value="{{ $e->id }}" @checked(in_array($e->id, $picked))> {{ $e->name }}</label>
            @endforeach
        </div>
    @endif

    <div class="form-grid">
        <div class="field"><label for="meta_title">Judul SEO</label><input id="meta_title" type="text" name="meta_title" value="{{ old('meta_title', $group->meta_title) }}" maxlength="70"><div class="hint">Kosong = pakai nama produk.</div></div>
        <div class="field"><label for="meta_description">Deskripsi SEO</label><input id="meta_description" type="text" name="meta_description" value="{{ old('meta_description', $group->meta_description) }}" maxlength="320"></div>
    </div>
    <div class="row" style="margin-bottom:1rem">
        <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $group->is_active))> Aktif</label>
        <label class="check"><input type="checkbox" name="is_online" value="1" @checked(old('is_online', $group->is_online))> Tampil di toko online</label>
        <label class="check"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $group->is_featured))> Produk pilihan di beranda</label>
        @unless ($isNew)<label class="check"><input type="checkbox" name="regenerate_slug" value="1"> Perbarui alamat halaman sesuai nama baru</label>@endunless
    </div>
    <button class="btn">{{ $isNew ? 'Simpan dan lanjut' : 'Simpan perubahan' }}</button>
</form>

@unless ($isNew)
<section class="card">
    <h2>Foto produk</h2>
    <p class="muted">Foto pertama menjadi sampul di kartu produk dan foto utama di halaman detail. Foto dipotong persegi untuk kartu dan tetap utuh di halaman detail. JPG, PNG, atau WebP, maksimal 5 MB per foto.</p>
    @if ($group->images->isNotEmpty())
        <div class="gallery-admin">
            @foreach ($group->images as $img)
                <figure class="gimg">
                    <img src="{{ $img->url('card') }}" alt="{{ $img->alt }}" loading="lazy">
                    @if ($loop->first)<span class="badge badge--ok gimg__cover">Sampul</span>@endif
                    <form method="post" action="{{ route('admin.catalog.images.update', [$group, $img]) }}" class="gimg__edit">
                        @csrf @method('PUT')
                        <input type="text" name="alt" value="{{ $img->alt }}" maxlength="160" placeholder="Keterangan foto" aria-label="Keterangan foto {{ $loop->iteration }}">
                        <select name="product_id" aria-label="Foto untuk variasi">
                            <option value="">Semua variasi</option>
                            @foreach ($group->variants as $v)<option value="{{ $v->id }}" @selected($img->product_id === $v->id)>{{ $v->variant_name ?: $v->name }}</option>@endforeach
                        </select>
                        <button class="btn btn--ghost btn--sm">Simpan</button>
                    </form>
                    <div class="gimg__tools">
                        <form method="post" action="{{ route('admin.catalog.images.move', [$group, $img]) }}">@csrf <button name="to" value="left" class="btn btn--ghost btn--sm" aria-label="Geser ke kiri" @disabled($loop->first)>‹</button> <button name="to" value="right" class="btn btn--ghost btn--sm" aria-label="Geser ke kanan" @disabled($loop->last)>›</button> @unless ($loop->first)<button name="to" value="first" class="btn btn--ghost btn--sm">Jadikan sampul</button>@endunless</form>
                        <form method="post" action="{{ route('admin.catalog.images.destroy', [$group, $img]) }}" onsubmit="return confirm('Hapus foto ini?')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Hapus</button></form>
                    </div>
                </figure>
            @endforeach
        </div>
    @else
        <p class="muted">Belum ada foto. Toko menampilkan inisial produk sampai foto diunggah.</p>
    @endif
    <form method="post" enctype="multipart/form-data" action="{{ route('admin.catalog.images.store', $group) }}" class="row" style="margin-top:1rem">
        @csrf
        <div class="field" style="margin:0"><label for="photos">Tambah foto (boleh banyak sekaligus)</label><input id="photos" type="file" name="photos[]" accept="image/*" multiple required></div>
        <button class="btn">Unggah</button>
    </form>
</section>

<section class="card scroll">
    <h2>Variasi dan SKU</h2>
    <table>
        <thead><tr><th>Variasi</th><th>SKU</th><th>Pilihan</th><th class="num">Harga</th><th class="num">Stok</th><th>Tampil</th><th></th></tr></thead>
        <tbody>
        @forelse ($group->variants as $v)
            <tr>
                <td>{{ $v->variant_name ?: '(utama)' }}</td>
                <td>{{ $v->sku }}@if ($v->barcode)<div class="muted">{{ $v->barcode }}</div>@endif</td>
                <td>{{ collect($v->options)->implode(' / ') ?: '-' }}</td>
                <td class="num">{{ \App\Support\Rupiah::format($v->price) }}</td>
                <td class="num" data-state="{{ $v->stockState() }}"><span class="badge">{{ \App\Support\Qty::pretty($v->stock_qty) }} {{ $v->unit }}</span></td>
                <td>{{ $v->is_active ? ($v->is_online ? 'Kasir + web' : 'Kasir saja') : 'Nonaktif' }}</td>
                <td class="num"><a class="btn btn--ghost btn--sm" href="{{ route('admin.products.edit', $v) }}">Stok dan detail</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Belum ada variasi. Tambahkan minimal satu supaya produk bisa dijual.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3 style="margin-top:1.4rem">Tambah variasi</h3>
    <form method="post" action="{{ route('admin.catalog.variants.store', $group) }}">
        @csrf
        <div class="form-grid">
            <div class="field"><label for="variant_name">Nama variasi</label><input id="variant_name" type="text" name="variant_name" required maxlength="80" placeholder="Contoh: 500 g halus" value="{{ old('variant_name') }}"></div>
            <div class="field"><label for="v_sku">SKU</label><input id="v_sku" type="text" name="sku" required maxlength="60" value="{{ old('sku') }}"></div>
            <div class="field"><label for="v_barcode">Barcode</label><input id="v_barcode" type="text" name="barcode" maxlength="60" value="{{ old('barcode') }}"></div>
            <div class="field"><label for="v_unit">Satuan</label><input id="v_unit" type="text" name="unit" required maxlength="20" value="{{ old('unit', $group->variants->last()->unit ?? 'pcs') }}"></div>
            <div class="field"><label for="v_price">Harga jual (Rp)</label><input id="v_price" type="number" name="price" min="0" required value="{{ old('price') }}"></div>
            <div class="field"><label for="v_min">Batas stok menipis</label><input id="v_min" type="number" step="0.001" min="0" name="min_stock" value="{{ old('min_stock') }}"></div>
            @foreach ((array) $group->option_names as $n)
                <div class="field"><label for="opt_{{ $loop->index }}">{{ $n }}</label><input id="opt_{{ $loop->index }}" type="text" name="options[{{ $n }}]" maxlength="60" value="{{ old("options.$n") }}"></div>
            @endforeach
            <div class="field"><label for="v_qty">Stok awal (opsional)</label><input id="v_qty" type="number" step="0.001" min="0" name="initial_qty" value="{{ old('initial_qty') }}"></div>
            <div class="field"><label for="v_cost">Harga beli / HPP per satuan (Rp)</label><input id="v_cost" type="number" min="0" name="initial_cost" value="{{ old('initial_cost') }}"></div>
        </div>
        <button class="btn">Tambah variasi</button>
        <p class="hint">Stok awal dicatat sebagai modal pemilik, lengkap dengan jurnalnya. Untuk pembelian sungguhan, pakai "Stok masuk" di halaman SKU.</p>
    </form>
</section>
@endunless
@endsection
