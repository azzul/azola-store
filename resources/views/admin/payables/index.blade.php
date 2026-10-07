@extends('layouts.admin')
@section('title', 'Hutang')
@section('heading', 'Hutang')
@section('actions')<a class="btn" href="{{ route('admin.supplier-payments.create') }}">Bayar hutang</a><a class="btn btn--ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Unduh CSV</a>@endsection
@section('content')
<div class="kpis">
    <div class="kpi {{ $total > 0 ? 'kpi--warn' : '' }}"><b>@rp($total)</b><span>Total hutang</span></div>
    <div class="kpi"><b>@rp($buckets['current'])</b><span>Belum jatuh tempo</span></div>
    <div class="kpi {{ ($buckets['d30'] + $buckets['d60'] + $buckets['over']) > 0 ? 'kpi--bad' : '' }}"><b>@rp($buckets['d30'] + $buckets['d60'] + $buckets['over'])</b><span>Lewat jatuh tempo (1-30: @rp($buckets['d30']) · 31-60: @rp($buckets['d60']) · >60: @rp($buckets['over']))</span></div>
</div>
<form class="filters" method="get"><div><label for="supplier">Supplier</label><select id="supplier" name="supplier"><option value="">Semua</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier') == $s->id)>{{ $s->name }}</option>@endforeach</select></div><button class="btn btn--ghost">Terapkan</button></form>
<div class="grid grid--wide">
<section class="card scroll"><h2>Faktur belum lunas</h2>
    <table><thead><tr><th>Faktur</th><th>Supplier</th><th>Jatuh tempo</th><th class="num">Total</th><th class="num">Sisa</th><th></th></tr></thead><tbody>
    @forelse ($open as $p)
        <tr><td><a href="{{ route('admin.purchases.show', $p) }}">{{ $p->number }}</a><div class="muted">{{ $p->date->format('d/m/Y') }}</div></td><td>{{ $p->supplier?->name }}</td>
        <td>{{ $p->due_date?->format('d/m/Y') ?: '-' }}@if ($p->isOverdue()) <span class="badge badge--bad">lewat {{ $p->due_date->diffInDays(today()) }} hari</span>@endif</td>
        <td class="num">@rp($p->grand_total)</td><td class="num">@rp($p->outstanding())</td>
        <td class="no-print"><a href="{{ route('admin.supplier-payments.create', ['supplier' => $p->supplier_id, 'purchase' => $p->id]) }}">Bayar</a></td></tr>
    @empty<tr><td colspan="6" class="muted">Tidak ada hutang. 🎉</td></tr>@endforelse
    </tbody></table>
</section>
<section class="card scroll"><h2>Per supplier</h2>
    <table><thead><tr><th>Supplier</th><th class="num">Sisa</th></tr></thead><tbody>
    @forelse ($bySupplier as $row)
        <tr><td>@if ($row['supplier'])<a href="{{ route('admin.cards.payable', ['supplier' => $row['supplier']->id]) }}">{{ $row['supplier']->name }}</a>@else Tanpa supplier @endif<div class="muted">{{ $row['count'] }} faktur @if ($row['overdue'])· <span class="neg">lewat tempo @rp($row['overdue'])</span>@endif</div></td><td class="num">@rp($row['total'])</td></tr>
    @empty<tr><td class="muted">-</td></tr>@endforelse
    </tbody></table>
</section>
</div>
@endsection
