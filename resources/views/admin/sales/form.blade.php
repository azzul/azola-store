@extends('layouts.admin')
@section('title', 'Input penjualan')
@section('heading', 'Input penjualan')
@section('content')
<div class="help">Untuk penjualan di luar kasir (grosir, titipan, kirim). Harga mengikuti level harga customer; ubah harga ke bawah untuk memberi potongan. Bayar sebagian atau kosongkan jumlah bayar untuk mencatat <strong>piutang</strong>.</div>
<form method="post" action="{{ route('admin.sales.store') }}">@csrf
<section class="card"><div class="form-grid form-grid--tight">
    <div class="field"><label for="buyer_id">Customer</label><select id="buyer_id" name="buyer_id"><option value="">Pelanggan umum</option>@foreach ($customers as $c)<option value="{{ $c->id }}" data-level="{{ $c->price_level_id }}" @selected((int) old('buyer_id') === $c->id)>{{ $c->name }}{{ $c->phone ? ' · '.$c->phone : '' }}</option>@endforeach</select></div>
    <div class="field"><label for="customer_name">Nama (bila tidak di master)</label><input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" maxlength="120"></div>
    <div class="field"><label for="customer_phone">Telepon</label><input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" maxlength="40"></div>
    <div class="field"><label for="price_level_id">Level harga</label><select id="price_level_id" name="price_level_id" data-price-level><option value="">Ikuti customer</option>@foreach ($levels as $l)<option value="{{ $l->id }}" @selected((int) old('price_level_id') === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
</div><p class="hint">Harga di tabel diisi dari level harga saat barang ditambahkan. Ganti customer/level sebelum memilih barang.</p></section>
<section class="card"><h2>Barang</h2><x-admin.lines name="items" mode="price" price-from="price" :initial="$initial" />
    <div class="totals"><div class="grand"><span>Subtotal barang</span><b data-lines-grand="items">Rp0</b></div></div></section>
<section class="card"><div class="form-grid form-grid--tight">
    <div class="field"><label for="order_discount">Diskon total (Rp)</label><input id="order_discount" type="number" min="0" name="order_discount" value="{{ old('order_discount', 0) }}"></div>
    <div class="field"><label for="shipping_fee">Ongkir (Rp)</label><input id="shipping_fee" type="number" min="0" name="shipping_fee" value="{{ old('shipping_fee', 0) }}"></div>
    <div class="field"><label for="payment_method">Cara bayar</label><select id="payment_method" name="payment_method">@foreach ($methods as $m)<option value="{{ $m }}" @selected(old('payment_method', 'cash') === $m)>{{ $m }}</option>@endforeach</select></div>
    <div class="field"><label for="paid_total">Dibayar sekarang (Rp)</label><input id="paid_total" type="number" min="0" name="paid_total" value="{{ old('paid_total') }}" placeholder="kosong = lunas"></div>
    <div class="field"><label for="due_date">Jatuh tempo piutang</label><input id="due_date" type="date" name="due_date" value="{{ old('due_date') }}"></div>
    <div class="field"><label for="notes">Catatan</label><input id="notes" name="notes" value="{{ old('notes') }}" maxlength="500"></div>
</div></section>
<div class="actions"><button class="btn">Simpan penjualan</button><a class="btn btn--ghost" href="{{ route('admin.sales.index') }}">Batal</a></div>
</form>
@push('scripts')
<script>document.getElementById('buyer_id').addEventListener('change',function(){var o=this.options[this.selectedIndex];document.getElementById('price_level_id').value=o.dataset.level||'';});</script>
@endpush
@endsection
