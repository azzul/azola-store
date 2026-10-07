@extends('layouts.admin')
@section('title', 'Jurnal neraca')
@section('heading', 'Jurnal neraca')
@section('content')
<x-admin.period :from="$asOf" :to="$asOf" :single="true" :csv="true" label="Tampilkan neraca" />
<div class="kpis">
    <div class="kpi"><b>@rp($sheet['totals']['asset'])</b><span>Total aset</span></div>
    <div class="kpi"><b>@rp($sheet['totals']['liability'] + $sheet['totals']['equity'])</b><span>Kewajiban + modal</span></div>
    <div class="kpi {{ $sheet['totals']['balanced'] ? '' : 'kpi--bad' }}"><b>{{ $sheet['totals']['balanced'] ? 'Seimbang' : 'TIDAK seimbang' }}</b><span>Per {{ \Carbon\Carbon::parse($asOf)->format('d/m/Y') }}</span></div>
</div>
<div class="grid grid--2">
<section class="card scroll"><h2>Aset</h2><table><tbody>
    @forelse ($sheet['asset'] as $r)<tr><td>{{ $r['account']->code }} {{ $r['account']->name }}</td><td class="num">@rp($r['amount'])</td></tr>@empty<tr><td class="muted">Belum ada aset.</td></tr>@endforelse
</tbody><tfoot><tr><td>Total aset</td><td class="num">@rp($sheet['totals']['asset'])</td></tr></tfoot></table></section>
<section class="card scroll"><h2>Kewajiban</h2><table><tbody>
    @forelse ($sheet['liability'] as $r)<tr><td>{{ $r['account']->code }} {{ $r['account']->name }}</td><td class="num">@rp($r['amount'])</td></tr>@empty<tr><td class="muted">Tidak ada kewajiban.</td></tr>@endforelse
    <tr class="sub"><td>Total kewajiban</td><td class="num">@rp($sheet['totals']['liability'])</td></tr>
</tbody></table>
<h2>Modal</h2><table><tbody>
    @foreach ($sheet['equity'] as $r)<tr><td>{{ $r['account']->code }} {{ $r['account']->name }}</td><td class="num">@rp($r['amount'])</td></tr>@endforeach
    <tr><td>Laba berjalan</td><td class="num">@rp($sheet['earnings'])</td></tr>
</tbody><tfoot><tr><td>Total modal</td><td class="num">@rp($sheet['totals']['equity'])</td></tr></tfoot></table></section>
</div>
@endsection
