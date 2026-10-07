@extends('layouts.admin')
@section('title', 'Penyesuaian stok')
@section('heading', 'Penyesuaian stok')
@section('content')
<div class="help">Untuk barang rusak, hilang, kedaluwarsa, penyusutan, atau koreksi. Isi <strong>selisih</strong>: angka minus untuk mengurangi, plus untuk menambah. Kerusakan/kehilangan masuk ke akun <em>Kerugian persediaan</em>; koreksi ke <em>Penyesuaian persediaan</em>. Untuk menghitung ulang seluruh stok, pakai <a href="{{ route('admin.opnames.create') }}">Stok opname</a>.</div>
<form method="post" action="{{ route('admin.adjustments.store') }}">@csrf
<section class="card"><div class="form-grid form-grid--tight">
    <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
    <div class="field"><label for="reason">Alasan</label><select id="reason" name="reason">@foreach ($reasons as $k => $v)<option value="{{ $k }}" @selected(old('reason') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label for="warehouse_id">Gudang</label><select id="warehouse_id" name="warehouse_id">@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
    <div class="field"><label for="note">Catatan</label><input id="note" name="note" value="{{ old('note') }}" maxlength="200"></div>
</div></section>
<section class="card"><h2>Barang</h2><x-admin.lines name="items" mode="delta" :initial="$initial" /></section>
<div class="actions"><button class="btn">Simpan penyesuaian</button><a class="btn btn--ghost" href="{{ route('admin.adjustments.index') }}">Batal</a></div>
</form>
@endsection
