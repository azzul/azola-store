@extends('layouts.admin')
@section('title', 'Konversi satuan')
@section('heading', 'Konversi satuan')
@section('content')
<div class="help">Contoh: 1 dus = 12 pcs. Saat input pembelian, retur, atau penjualan Anda bisa memilih satuan dus; stok tetap dihitung dalam pcs. Harga khusus per dus boleh dikosongkan (otomatis harga pcs × isi).</div>
<section class="card">
    <h2>Tambah konversi</h2>
    <form method="post" action="{{ route('admin.conversions.store') }}">@csrf
        <div class="form-grid">
            <div class="field"><label for="product_id">Produk</label>
                <select id="product_id" name="product_id" required>
                    <option value="">Pilih produk</option>
                    @foreach ($products as $p)<option value="{{ $p->id }}" @selected((int) old('product_id', $productId) === $p->id)>{{ $p->name }}{{ $p->variant_name ? ' - '.$p->variant_name : '' }} ({{ $p->sku }}, {{ $p->unit }})</option>@endforeach
                </select></div>
            <div class="field"><label for="unit">Satuan besar</label><input id="unit" name="unit" list="unit-list" value="{{ old('unit') }}" maxlength="20" required placeholder="dus"><datalist id="unit-list">@foreach ($units as $u)<option value="{{ $u }}">@endforeach</datalist></div>
            <div class="field"><label for="factor">Isi (satuan dasar)</label><input id="factor" type="number" step="any" min="0.001" name="factor" value="{{ old('factor') }}" required placeholder="12"></div>
            <div class="field"><label for="barcode">Barcode satuan besar</label><input id="barcode" name="barcode" value="{{ old('barcode') }}" maxlength="60"></div>
            <div class="field"><label for="price">Harga jual per satuan besar</label><input id="price" type="number" min="0" name="price" value="{{ old('price') }}" placeholder="otomatis"></div>
        </div>
        <button class="btn">Simpan konversi</button>
    </form>
</section>
<form class="filters" method="get"><div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Produk atau SKU"></div><button class="btn btn--ghost">Cari</button></form>
<section class="card scroll">
    <table>
        <thead><tr><th>Produk</th><th>Konversi</th><th>Barcode</th><th class="num">Harga jual</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $c)
            <tr>
                <td>{{ $c->product->name }}{{ $c->product->variant_name ? ' - '.$c->product->variant_name : '' }}<div class="muted">{{ $c->product->sku }}</div></td>
                <td colspan="3">
                    <form method="post" action="{{ route('admin.conversions.update', $c) }}" class="inline-form">@csrf @method('PUT')
                        1 {{ $c->unit }} = <input type="number" step="any" min="0.001" name="factor" value="{{ (float) $c->factor }}" aria-label="Isi" style="width:6rem"> {{ $c->product->unit }}
                        <input name="barcode" value="{{ $c->barcode }}" placeholder="Barcode" aria-label="Barcode">
                        <input type="number" min="0" name="price" value="{{ $c->price }}" placeholder="Harga: @rp($c->sellPrice())" aria-label="Harga jual">
                        <button class="btn btn--ghost btn--sm">Simpan</button>
                    </form>
                </td>
                <td><form method="post" action="{{ route('admin.conversions.destroy', $c) }}" onsubmit="return confirm('Hapus konversi ini?')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Hapus</button></form></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada konversi satuan.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $rows->links('pagination.store') }}
</section>
@endsection
