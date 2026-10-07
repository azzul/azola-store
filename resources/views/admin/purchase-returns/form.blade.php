@extends('layouts.admin')
@section('title', 'Retur pembelian')
@section('heading', 'Retur pembelian'.($purchase ? ' dari '.$purchase->number : ''))
@section('content')
<div class="help">Barang yang diretur keluar dari stok. Pilih cara penyelesaian: <strong>potong hutang</strong> (mengurangi faktur yang belum lunas), uang kembali <strong>tunai/transfer</strong>, atau <strong>jadi piutang supplier</strong> bila uangnya dikembalikan nanti. HPP dihitung ulang otomatis.</div>
<form method="post" action="{{ route('admin.purchase-returns.store') }}">@csrf
<input type="hidden" name="purchase_id" value="{{ $purchase?->id }}">
<section class="card">
    <div class="form-grid form-grid--tight">
        <div class="field"><label for="supplier_id">Supplier</label><select id="supplier_id" name="supplier_id" required><option value="">Pilih supplier</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id', $supplierId) === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
        <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
        <div class="field"><label for="warehouse_id">Barang keluar dari gudang</label><select id="warehouse_id" name="warehouse_id">@foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected((int) old('warehouse_id', $purchase?->warehouse_id) === $w->id)>{{ $w->name }}</option>@endforeach</select></div>
        <div class="field"><label for="settlement">Penyelesaian</label><select id="settlement" name="settlement">@foreach (['payable' => 'Potong hutang', 'cash' => 'Uang kembali tunai', 'bank' => 'Uang kembali transfer', 'receivable' => 'Jadi piutang supplier'] as $k => $v)<option value="{{ $k }}" @selected(old('settlement', 'payable') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="field"><label for="reason">Alasan</label><input id="reason" name="reason" value="{{ old('reason') }}" maxlength="200" placeholder="mis. rusak saat diterima"></div>
    </div>
</section>
<section class="card">
    <h2>Barang yang diretur</h2>
    <x-admin.lines name="items" mode="price" price-from="cost" :initial="$initial" />
    <div class="totals"><div class="grand"><span>Total retur</span><b data-lines-grand="items">Rp0</b></div></div>
    @if ($purchase)<p class="hint">Jumlah awal 0: isi barang yang benar-benar dikembalikan. Retur tidak boleh melebihi jumlah yang dibeli.</p>@endif
</section>
<div class="actions"><button class="btn">Simpan retur</button><a class="btn btn--ghost" href="{{ route('admin.purchase-returns.index') }}">Batal</a></div>
</form>
@endsection
