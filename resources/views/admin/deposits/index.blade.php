@extends('layouts.admin')
@section('title', 'DP customer')
@section('heading', 'DP customer')
@section('actions')<a class="btn" href="{{ route('admin.deposits.create') }}">Terima DP</a>@endsection
@section('content')
<div class="help">DP (uang muka) adalah <strong>kewajiban</strong> sampai dipakai untuk melunasi pesanan atau dikembalikan. Tidak dihitung sebagai pendapatan.</div>
<div class="kpis"><div class="kpi {{ $balance > 0 ? 'kpi--warn' : '' }}"><b>@rp($balance)</b><span>Total saldo DP yang belum terpakai</span></div></div>
<form class="filters" method="get">
    <div><label for="customer">Customer</label><select id="customer" name="customer"><option value="">Semua</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(request('customer') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div><label for="open">Tampilkan</label><select id="open" name="open"><option value="">Semua</option><option value="1" @selected(request('open'))>Masih bersaldo</option></select></div>
    <button class="btn btn--ghost">Terapkan</button>
</form>
<section class="card scroll"><table>
    <thead><tr><th>Nomor</th><th>Tanggal</th><th>Customer</th><th>Jenis</th><th class="num">Jumlah</th><th class="num">Terpakai</th><th class="num">Dikembalikan</th><th class="num">Saldo</th></tr></thead><tbody>
    @forelse ($deposits as $d)
        <tr><td><a href="{{ route('admin.deposits.show', $d) }}">{{ $d->number }}</a></td><td>{{ $d->date->format('d/m/Y') }}</td><td>{{ $d->customer?->name ?? $d->customer_name ?? '-' }}</td><td>{{ ['dp' => 'DP', 'overpay' => 'Kelebihan transfer', 'return' => 'Dari retur'][$d->kind] ?? $d->kind }}</td>
        <td class="num">@rp($d->amount)</td><td class="num">@rp($d->used_total)</td><td class="num">@rp($d->refunded_total)</td><td class="num"><b>@rp($d->balance())</b></td></tr>
    @empty<tr><td colspan="8" class="muted">Belum ada DP.</td></tr>@endforelse
</tbody></table>{{ $deposits->links('pagination.store') }}</section>
@endsection
