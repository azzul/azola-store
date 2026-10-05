@extends('layouts.admin')
@section('title', 'Pesanan')
@section('heading', 'Pesanan')
@section('content')
<form class="filters" method="get">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nomor, nama, atau telepon"></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="">Semua</option>@foreach (['pending' => 'Menunggu', 'completed' => 'Selesai', 'cancelled' => 'Batal'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label for="channel">Kanal</label><select id="channel" name="channel"><option value="">Semua</option>@foreach (['web' => 'Web', 'pos_desktop' => 'Pos Desktop', 'pos_android' => 'Pos Android', 'admin' => 'Admin'] as $k => $v)<option value="{{ $k }}" @selected(request('channel') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label for="bayar">Pembayaran</label><select id="bayar" name="bayar"><option value="">Semua</option>@foreach (['unpaid' => 'Belum bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas'] as $k => $v)<option value="{{ $k }}" @selected(request('bayar') === $k)>{{ $v }}</option>@endforeach</select></div>
    <button class="btn btn--ghost">Terapkan</button>
</form>
<section class="card">
    @include('admin.orders.table')
    {{ $orders->links('pagination.store') }}
</section>
@endsection
