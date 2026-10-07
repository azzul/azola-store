@extends('layouts.admin')
@section('title', 'Jurnal laba rugi')
@section('heading', 'Jurnal laba rugi')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true" />
<div class="kpis">
    <div class="kpi"><b>@rp($s['totals']['revenue'])</b><span>Pendapatan</span></div>
    <div class="kpi"><b>@rp($s['totals']['gross'])</b><span>Laba kotor</span></div>
    <div class="kpi {{ $s['totals']['net'] < 0 ? 'kpi--bad' : '' }}"><b>@rp($s['totals']['net'])</b><span>Laba bersih</span></div>
</div>
<section class="card scroll"><table>
    <tbody>
    <tr class="sub"><td colspan="2">Pendapatan</td></tr>
    @forelse ($s['revenue'] as $r)<tr><td>{{ $r['account']->code }} {{ $r['account']->name }}</td><td class="num">@rp($r['amount'])</td></tr>@empty<tr><td colspan="2" class="muted">Tidak ada pendapatan.</td></tr>@endforelse
    <tr><td><b>Total pendapatan</b></td><td class="num"><b>@rp($s['totals']['revenue'])</b></td></tr>
    <tr class="sub"><td colspan="2">Harga pokok penjualan</td></tr>
    @foreach ($s['cogs'] as $r)<tr><td>{{ $r['account']->code }} {{ $r['account']->name }}</td><td class="num">@rp($r['amount'])</td></tr>@endforeach
    <tr><td><b>Total HPP</b></td><td class="num"><b>@rp($s['totals']['cogs'])</b></td></tr>
    <tr><td><b>Laba kotor</b></td><td class="num"><b>@rp($s['totals']['gross'])</b></td></tr>
    <tr class="sub"><td colspan="2">Beban operasional</td></tr>
    @forelse ($s['expense'] as $r)<tr><td>{{ $r['account']->code }} {{ $r['account']->name }}</td><td class="num">@rp($r['amount'])</td></tr>@empty<tr><td colspan="2" class="muted">Tidak ada beban.</td></tr>@endforelse
    <tr><td><b>Total beban</b></td><td class="num"><b>@rp($s['totals']['expense'])</b></td></tr>
    </tbody>
    <tfoot><tr><td>Laba (rugi) bersih</td><td class="num">@rp($s['totals']['net'])</td></tr></tfoot>
</table></section>
@endsection
