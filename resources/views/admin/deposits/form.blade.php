@extends('layouts.admin')
@section('title', 'Terima DP')
@section('heading', 'Terima DP customer')
@section('content')
<form class="card" method="post" action="{{ route('admin.deposits.store') }}">@csrf
<div class="form-grid form-grid--tight">
    <div class="field"><label for="customer_id">Customer</label><select id="customer_id" name="customer_id"><option value="">Isi nama manual</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((int) old('customer_id', $customerId) === $c->id)>{{ $c->name }}{{ $c->phone ? ' · '.$c->phone : '' }}</option>@endforeach</select></div>
    <div class="field"><label for="customer_name">Nama (bila bukan di master)</label><input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" maxlength="120"></div>
    <div class="field"><label for="amount">Jumlah (Rp)</label><input id="amount" type="number" min="1" name="amount" value="{{ old('amount') }}" required></div>
    <div class="field"><label for="method">Diterima lewat</label><select id="method" name="method">@foreach (['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'debit' => 'Debit'] as $k => $v)<option value="{{ $k }}" @selected(old('method') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
    <div class="field"><label for="note">Catatan</label><input id="note" name="note" value="{{ old('note') }}" maxlength="200" placeholder="mis. DP pesanan seragam"></div>
</div>
<div class="actions"><button class="btn">Simpan DP</button><a class="btn btn--ghost" href="{{ route('admin.deposits.index') }}">Batal</a></div>
</form>
@endsection
