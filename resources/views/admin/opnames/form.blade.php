@extends('layouts.admin')
@section('title', 'Stok opname baru')
@section('heading', 'Stok opname baru')
@section('content')
<div class="help">Sistem mencatat stok saat ini sebagai acuan, lalu Anda mengisi hasil hitung fisik. Perhitungan boleh dicicil dan disimpan berkali-kali; selisih baru masuk pembukuan saat <strong>difinalkan</strong>.</div>
<form class="card" method="post" action="{{ route('admin.opnames.store') }}">@csrf
<div class="form-grid form-grid--tight">
    <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
    <div class="field"><label for="warehouse_id">Gudang</label><select id="warehouse_id" name="warehouse_id">@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
    <div class="field"><label for="category_id">Hanya kategori</label><select id="category_id" name="category_id"><option value="">Semua kategori</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label for="note">Catatan</label><input id="note" name="note" maxlength="200" value="{{ old('note') }}"></div>
</div>
<div class="actions"><button class="btn">Mulai opname</button><a class="btn btn--ghost" href="{{ route('admin.opnames.index') }}">Batal</a></div>
</form>
@endsection
