@extends('layouts.admin')
@php($isNew = ! $supplier->exists)
@section('title', $isNew ? 'Supplier baru' : 'Ubah supplier')
@section('heading', $isNew ? 'Supplier baru' : 'Ubah '.$supplier->name)
@section('content')
<form method="post" action="{{ $isNew ? route('admin.suppliers.store') : route('admin.suppliers.update', $supplier) }}" class="card">@csrf @if (! $isNew) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="name">Nama supplier</label><input id="name" name="name" value="{{ old('name', $supplier->name) }}" maxlength="150" required></div>
        <div class="field"><label for="code">Kode (opsional)</label><input id="code" name="code" value="{{ old('code', $supplier->code) }}" maxlength="20"></div>
        <div class="field"><label for="contact">Nama kontak</label><input id="contact" name="contact" value="{{ old('contact', $supplier->contact) }}" maxlength="100"></div>
        <div class="field"><label for="phone">Telepon / WhatsApp</label><input id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" maxlength="40"></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $supplier->email) }}" maxlength="120"></div>
        <div class="field"><label for="term_days">Tempo bayar bawaan (hari)</label><input id="term_days" type="number" min="0" max="365" name="term_days" value="{{ old('term_days', $supplier->term_days) }}"><div class="hint">Dipakai menghitung jatuh tempo pembelian kredit.</div></div>
    </div>
    <div class="field"><label for="address">Alamat</label><textarea id="address" name="address" maxlength="500">{{ old('address', $supplier->address) }}</textarea></div>
    <div class="field"><label for="note">Catatan</label><input id="note" name="note" value="{{ old('note', $supplier->note) }}" maxlength="500"></div>
    @unless ($isNew)<label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active))> Supplier aktif</label>@endunless
    <div class="actions"><button class="btn">Simpan</button><a class="btn btn--ghost" href="{{ $isNew ? route('admin.suppliers.index') : route('admin.suppliers.show', $supplier) }}">Batal</a></div>
</form>
@endsection
