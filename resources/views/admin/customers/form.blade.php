@extends('layouts.admin')
@php($isNew = ! $customer->exists)
@section('title', $isNew ? 'Customer baru' : 'Ubah customer')
@section('heading', $isNew ? 'Customer baru' : 'Ubah '.$customer->name)
@section('content')
<form method="post" action="{{ $isNew ? route('admin.customers.store') : route('admin.customers.update', $customer) }}" class="card">@csrf @if (! $isNew) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="name">Nama</label><input id="name" name="name" value="{{ old('name', $customer->name) }}" maxlength="150" required></div>
        <div class="field"><label for="phone">Telepon / WhatsApp</label><input id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" maxlength="40" inputmode="tel"><div class="hint">Dipakai mengenali customer di kasir dan web.</div></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $customer->email) }}" maxlength="120"></div>
        <div class="field"><label for="price_level_id">Level harga</label>
            <select id="price_level_id" name="price_level_id"><option value="">Ecer (dasar)</option>@foreach ($levels->where('is_default', false) as $l)<option value="{{ $l->id }}" @selected((int) old('price_level_id', $customer->price_level_id) === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
        <div class="field"><label for="credit_limit">Batas piutang (Rp)</label><input id="credit_limit" type="number" min="0" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit) }}"><div class="hint">0 = tidak boleh berutang.</div></div>
    </div>
    <div class="field"><label for="address">Alamat</label><textarea id="address" name="address" maxlength="500">{{ old('address', $customer->address) }}</textarea></div>
    <div class="field"><label for="note">Catatan</label><input id="note" name="note" value="{{ old('note', $customer->note) }}" maxlength="500"></div>
    @unless ($isNew)<label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active))> Customer aktif</label>@endunless
    <div class="actions"><button class="btn">Simpan</button><a class="btn btn--ghost" href="{{ $isNew ? route('admin.customers.index') : route('admin.customers.show', $customer) }}">Batal</a></div>
</form>
@endsection
