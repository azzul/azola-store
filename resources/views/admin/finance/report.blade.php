@extends('layouts.admin')
@section('title', 'Laporan')
@section('heading', 'Laporan')
@section('content')
<form class="filters" method="get">
    <div><label for="from">Dari</label><input id="from" type="date" name="from" value="{{ $from }}"></div>
    <div><label for="to">Sampai</label><input id="to" type="date" name="to" value="{{ $to }}"></div>
    <button class="btn btn--ghost">Tampilkan</button>
</form>
<div class="kpis">
    <div class="kpi"><b>{{ \App\Support\Rupiah::format($revenue) }}</b><span>Pendapatan</span></div>
    <div class="kpi"><b>{{ \App\Support\Rupiah::format($cogs) }}</b><span>Harga pokok penjualan</span></div>
    <div class="kpi"><b>{{ \App\Support\Rupiah::format($gross) }}</b><span>Laba kotor</span></div>
    <div class="kpi {{ $profit < 0 ? 'kpi--bad' : '' }}"><b>{{ \App\Support\Rupiah::format($profit) }}</b><span>Laba bersih (setelah selisih persediaan)</span></div>
</div>
<section class="card scroll">
    <h2>Neraca saldo {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</h2>
    <table>
        <thead><tr><th>Akun</th><th>Jenis</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead>
        <tbody>
        @foreach ($rows as $r)
            <tr><td>{{ $r['account']->code }} {{ $r['account']->name }}</td><td>{{ $r['account']->type }}</td><td class="num">{{ $r['debit'] ? \App\Support\Rupiah::format($r['debit']) : '' }}</td><td class="num">{{ $r['credit'] ? \App\Support\Rupiah::format($r['credit']) : '' }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Total {!! $debitTotal === $creditTotal ? '<span class="badge badge--ok">seimbang</span>' : '<span class="badge badge--bad">tidak seimbang</span>' !!}</td><td class="num">{{ \App\Support\Rupiah::format($debitTotal) }}</td><td class="num">{{ \App\Support\Rupiah::format($creditTotal) }}</td></tr></tfoot>
    </table>
    <p class="hint">Pajak penjualan dan ongkir yang ditagihkan tercatat sebagai kewajiban/pendapatan terpisah, jadi pendapatan di atas sudah termasuk pendapatan ongkir.</p>
</section>
@endsection
