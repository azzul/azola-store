@extends('layouts.admin')
@section('title', 'Laporan pembelian')
@section('heading', 'Laporan pembelian')
@section('actions')<a class="btn" href="{{ route('admin.purchases.create') }}">Input pembelian</a>@endsection
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nomor / faktur supplier"></div>
    <div><label for="supplier">Supplier</label><select id="supplier" name="supplier"><option value="">Semua</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div><label for="pay">Pembayaran</label><select id="pay" name="pay"><option value="">Semua</option><option value="unpaid" @selected(request('pay') === 'unpaid')>Masih hutang</option><option value="paid" @selected(request('pay') === 'paid')>Lunas</option></select></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="">Aktif</option><option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option></select></div>
</x-admin.period>
<div class="kpis">
    <div class="kpi"><b>{{ $summary->n }}</b><span>Faktur</span></div>
    <div class="kpi"><b>@rp($summary->total)</b><span>Total pembelian</span></div>
    <div class="kpi {{ $summary->total - $summary->paid > 0 ? 'kpi--warn' : '' }}"><b>@rp($summary->total - $summary->paid)</b><span>Masih hutang (periode ini)</span></div>
</div>
<section class="card scroll">
    <table>
        <thead><tr><th>Nomor</th><th>Tanggal</th><th>Supplier</th><th>Cara bayar</th><th class="num">Total</th><th class="num">Dibayar</th><th class="num">Sisa</th><th></th></tr></thead>
        <tbody>
        @forelse ($purchases as $p)
            <tr>
                <td><a href="{{ route('admin.purchases.show', $p) }}">{{ $p->number }}</a>@if ($p->supplier_invoice)<div class="muted">Faktur: {{ $p->supplier_invoice }}</div>@endif</td>
                <td>{{ $p->date->format('d/m/Y') }}</td>
                <td>{{ $p->supplier?->name ?? '-' }}</td>
                <td>{{ ['cash' => 'Tunai', 'bank' => 'Transfer', 'credit' => 'Kredit'][$p->payment_method] }}@if ($p->due_date)<div class="muted">Tempo {{ $p->due_date->format('d/m/Y') }}</div>@endif</td>
                <td class="num">@rp($p->grand_total)</td>
                <td class="num">@rp($p->paid_total)</td>
                <td class="num">@if ($p->isCancelled())<span class="badge badge--bad">batal</span>@elseif ($p->outstanding() > 0)@rp($p->outstanding())@if ($p->isOverdue()) <span class="badge badge--bad">lewat tempo</span>@endif @else<span class="badge badge--ok">lunas</span>@endif</td>
                <td class="no-print"><a href="{{ route('admin.purchases.edit', $p) }}">Ubah</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">Tidak ada pembelian pada periode ini. <a href="{{ route('admin.purchases.create') }}">Input pembelian</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $purchases->links('pagination.store') }}
</section>
@endsection
