@extends('layouts.admin')
@section('title', 'Rekap pendapatan')
@section('heading', 'Rekap pendapatan')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true" />
<div class="kpis">
    <div class="kpi"><b>@rp($recap['received'])</b><span>Uang diterima (setelah refund)</span></div>
    <div class="kpi"><b>@rp($recap['billed'])</b><span>Ditagihkan dari {{ $recap['orders'] }} transaksi</span></div>
    <div class="kpi {{ $recap['billed'] - $recap['received'] > 0 ? 'kpi--warn' : '' }}"><b>@rp($recap['billed'] - $recap['received'])</b><span>Belum diterima (piutang periode ini) / selisih retur</span></div>
</div>
<div class="grid grid--2">
<section class="card scroll"><h2>Per metode bayar</h2><table><thead><tr><th>Metode</th><th class="num">Transaksi</th><th class="num">Jumlah</th></tr></thead><tbody>
    @forelse ($methods as $k => $label)@if (isset($recap['by_method'][$k]))<tr><td>{{ $label }}</td><td class="num">{{ $recap['by_method'][$k]['count'] }}</td><td class="num">@rp($recap['by_method'][$k]['total'])</td></tr>@endif @empty @endforelse
    @if (! $recap['by_method'])<tr><td colspan="3" class="muted">Belum ada pembayaran pada periode ini.</td></tr>@endif
</tbody><tfoot><tr><td colspan="2">Total</td><td class="num">@rp($recap['received'])</td></tr></tfoot></table></section>
<section class="card scroll"><h2>Per kanal</h2><table><thead><tr><th>Kanal</th><th class="num">Pembayaran</th><th class="num">Jumlah</th></tr></thead><tbody>
    @forelse (['kasir' => 'Kasir (POS)', 'online' => 'Online (web)', 'admin' => 'Input admin'] as $k => $label)@if (isset($recap['by_channel'][$k]))<tr><td>{{ $label }}</td><td class="num">{{ $recap['by_channel'][$k]['count'] }}</td><td class="num">@rp($recap['by_channel'][$k]['total'])</td></tr>@endif @empty @endforelse
</tbody></table></section>
</div>
<section class="card scroll"><h2>Per hari</h2><table>
    <thead><tr><th>Tanggal</th>@foreach ($methods as $label)<th class="num">{{ $label }}</th>@endforeach<th class="num">Total</th></tr></thead><tbody>
    @forelse ($recap['by_day'] as $day => $row)<tr><td>{{ \Carbon\Carbon::parse($day)->format('d/m/Y') }}</td>@foreach ($methods as $k => $label)<td class="num">{{ ($row[$k] ?? 0) ? \App\Support\Rupiah::format($row[$k]) : '' }}</td>@endforeach<td class="num"><b>@rp($row['_total'])</b></td></tr>
    @empty<tr><td colspan="{{ count($methods) + 2 }}" class="muted">Belum ada data.</td></tr>@endforelse
</tbody></table></section>
@endsection
