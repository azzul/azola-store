@extends('layouts.admin')
@section('title', 'Retur penjualan')
@section('heading', 'Retur penjualan')
@section('actions')<a class="btn" href="{{ route('admin.sale-returns.create') }}">Retur baru</a>@endsection
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true" />
<div class="kpis"><div class="kpi"><b>@rp($total)</b><span>Total retur pada periode ini</span></div></div>
<section class="card scroll"><table>
    <thead><tr><th>Nomor</th><th>Tanggal</th><th>Faktur</th><th class="num">Total</th><th class="num">Potong piutang</th><th class="num">Dikembalikan</th><th>Alasan</th></tr></thead>
    <tbody>
    @forelse ($returns as $r)
        <tr><td><a href="{{ route('admin.sale-returns.show', $r) }}">{{ $r->number }}</a></td><td>{{ $r->date->format('d/m/Y') }}</td><td><a href="{{ route('admin.orders.show', $r->order_id) }}">{{ $r->order?->number }}</a></td>
        <td class="num">@rp($r->total)</td><td class="num">@rp($r->offset_total)</td><td class="num">@rp($r->paid_out){{ $r->refund_method ? ' ('.$r->refund_method.')' : '' }}</td><td>{{ $r->reason ?: '-' }}</td></tr>
    @empty<tr><td colspan="7" class="muted">Belum ada retur penjualan.</td></tr>@endforelse
    </tbody></table>{{ $returns->links('pagination.store') }}</section>
@endsection
