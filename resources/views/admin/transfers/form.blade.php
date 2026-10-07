@extends('layouts.admin')
@section('title', 'Kirim barang antar gudang')
@section('heading', 'Kirim barang antar gudang')
@section('content')
<div class="help">Stok langsung keluar dari gudang asal dan tercatat <strong>dalam perjalanan</strong> (tetap dinilai di persediaan). Saat barang tiba, catat penerimaannya; kekurangan dibukukan sebagai selisih.</div>
<form method="post" action="{{ route('admin.transfers.store') }}">@csrf
<section class="card">
    <div class="form-grid form-grid--tight">
        <div class="field"><label for="from_warehouse_id">Gudang asal</label><select id="from_warehouse_id" name="from_warehouse_id" required>@foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected((int) $from === $w->id)>{{ $w->name }}</option>@endforeach</select></div>
        <div class="field"><label for="to_warehouse_id">Gudang tujuan</label><select id="to_warehouse_id" name="to_warehouse_id" required><option value="">Pilih tujuan</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected((int) $to === $w->id)>{{ $w->name }}</option>@endforeach</select></div>
        <div class="field"><label for="date">Tanggal kirim</label><input id="date" type="date" name="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
        <div class="field"><label for="note">Catatan</label><input id="note" name="note" value="{{ old('note') }}" maxlength="200"></div>
    </div>
    @if ($warehouses->count() < 2)<p class="neg">Anda baru punya satu gudang. <a href="{{ route('admin.warehouses.index') }}">Tambah gudang</a> dulu.</p>@endif
</section>
<section class="card"><h2>Barang</h2><x-admin.lines name="items" mode="qty" :initial="$initial" /></section>
<div class="actions"><button class="btn">Kirim</button><a class="btn btn--ghost" href="{{ route('admin.transfers.index') }}">Batal</a></div>
</form>
@endsection
