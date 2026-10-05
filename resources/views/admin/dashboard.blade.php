@extends('layouts.admin')
@section('title', 'Ringkasan')
@section('heading', 'Ringkasan')
@section('content')
@php $max = max(1, collect($chart)->max('total')); @endphp
<div class="kpis">
    <div class="kpi"><b>{{ \App\Support\Rupiah::format($todayTotal) }}</b><span>Penjualan hari ini ({{ $todayCount }} transaksi)</span></div>
    <div class="kpi"><b>{{ \App\Support\Rupiah::format($monthTotal) }}</b><span>Penjualan bulan ini</span></div>
    <div class="kpi {{ $pendingWeb ? 'kpi--warn' : '' }}"><b>{{ $pendingWeb }}</b><span>Pesanan web menunggu</span></div>
    <div class="kpi"><b>{{ \App\Support\Rupiah::format($receivable) }}</b><span>Piutang belum dibayar</span></div>
    <div class="kpi"><b>{{ \App\Support\Rupiah::format($stockValue) }}</b><span>Nilai persediaan</span></div>
    <div class="kpi {{ $outCount ? 'kpi--bad' : ($lowCount ? 'kpi--warn' : '') }}"><b>{{ $outCount }} habis, {{ $lowCount }} menipis</b><span><a href="{{ route('admin.stock', ['tampil' => 'out']) }}">Lihat stok</a></span></div>
</div>

<div class="grid grid--2">
    <section class="card">
        <h2>Penjualan 7 hari terakhir</h2>
        <div class="chart" role="img" aria-label="Grafik penjualan 7 hari">
            @foreach ($chart as $col)
                <div class="chart__col" title="{{ $col['date'] }}: {{ \App\Support\Rupiah::format($col['total']) }}">
                    <em>{{ $col['total'] ? number_format($col['total'] / 1000, 0, ',', '.').'rb' : '' }}</em>
                    <div class="chart__bar" style="height: {{ round($col['total'] / $max * 100) }}%"></div>
                    <span>{{ $col['label'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2>Kesehatan data</h2>
        <p>
            @if ($reconciliation['ok'])
                <span class="badge badge--ok">Semua cocok</span> Stok, jurnal, dan pesanan saling cocok.
            @else
                <span class="badge badge--bad">Ada selisih</span> Periksa halaman rekonsiliasi sebelum melanjutkan.
            @endif
        </p>
        <p><a href="{{ route('admin.reconcile') }}">Buka rekonsiliasi</a></p>
        <h2>Penjualan bulan ini per kanal</h2>
        <table>
            @forelse ($channels as $c)
                <tr><td>{{ ['web' => 'Web', 'pos_desktop' => 'Azola Pos Desktop', 'pos_android' => 'Azola Pos Android', 'admin' => 'Admin'][$c->channel] ?? $c->channel }}</td><td class="num">{{ $c->n }} pesanan</td><td class="num">{{ \App\Support\Rupiah::format($c->total) }}</td></tr>
            @empty
                <tr><td class="muted">Belum ada penjualan bulan ini.</td></tr>
            @endforelse
        </table>
    </section>
</div>

<section class="card">
    <h2>Pesanan terbaru</h2>
    @include('admin.orders.table', ['orders' => $recent])
</section>
@endsection
