@extends('layouts.admin')
@section('title', 'Rekap kas harian')
@section('heading', 'Rekap kas harian')
@section('content')
<div class="help">Hitung uang di laci per pecahan, bandingkan dengan kas menurut sistem. Selisih dijurnal otomatis ke <em>Selisih kas</em> dan tanggal itu dikunci agar tidak ditutup dua kali.</div>
<x-admin.period :from="$date" :to="$date" :single="true" label="Lihat tanggal" />
<div class="kpis">
    <div class="kpi"><b>@rp($summary['opening'])</b><span>Kas awal</span></div>
    <div class="kpi"><b>@rp($summary['in'])</b><span>Kas masuk</span></div>
    <div class="kpi"><b>@rp($summary['out'])</b><span>Kas keluar</span></div>
</div>
<div class="kpis"><div class="kpi"><b>@rp($summary['expected'])</b><span>Seharusnya ada di laci</span></div></div>
@if ($closing)
    <section class="card"><p>Kas tanggal ini sudah ditutup: <a href="{{ route('admin.cash.show', $closing) }}">{{ $closing->number }}</a> · terhitung @rp($closing->counted) · selisih <b class="{{ $closing->difference < 0 ? 'neg' : '' }}">@rp($closing->difference)</b>.</p></section>
@else
<form class="card" method="post" action="{{ route('admin.cash.store') }}">@csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <h2>Hitung uang di laci</h2>
    <div class="scroll"><table><thead><tr><th>Pecahan</th><th>Jumlah lembar/keping</th><th class="num">Nilai</th></tr></thead><tbody>
    @foreach ($denominations as $v)<tr><td>@rp($v)</td><td><input class="js-den" type="number" min="0" name="denominations[{{ $v }}]" value="{{ old("denominations.$v", 0) }}" data-value="{{ $v }}" aria-label="Jumlah pecahan {{ $v }}" style="width:7rem"></td><td class="num js-den-sub">Rp0</td></tr>@endforeach
    <tr><td>Lainnya (Rp)</td><td><input id="other" type="number" min="0" name="other" value="{{ old('other', 0) }}" aria-label="Uang lainnya" style="width:9rem"></td><td></td></tr>
    </tbody><tfoot><tr><td colspan="2">Total terhitung</td><td class="num" id="counted">Rp0</td></tr><tr><td colspan="2">Selisih dengan sistem</td><td class="num" id="diff">Rp0</td></tr></tfoot></table></div>
    <div class="field"><label for="note">Catatan</label><input id="note" name="note" maxlength="200" value="{{ old('note') }}"></div>
    <div class="actions"><button class="btn" onclick="return confirm('Tutup kas tanggal ini? Selisih akan dijurnal.')">Tutup kas</button></div>
</form>
@push('scripts')
<script>
(function () {
    var fmt = new Intl.NumberFormat('id-ID'), expected = {{ (int) $summary['expected'] }};
    function calc() {
        var t = +document.getElementById('other').value || 0;
        document.querySelectorAll('.js-den').forEach(function (i) { var s = (+i.value || 0) * +i.dataset.value; t += s; i.closest('tr').querySelector('.js-den-sub').textContent = 'Rp' + fmt.format(s); });
        document.getElementById('counted').textContent = 'Rp' + fmt.format(t);
        var d = t - expected; document.getElementById('diff').textContent = (d < 0 ? '-' : '') + 'Rp' + fmt.format(Math.abs(d));
    }
    document.querySelectorAll('.js-den, #other').forEach(function (i) { i.addEventListener('input', calc); }); calc();
})();
</script>
@endpush
@endif
<section class="card scroll"><h2>Riwayat tutup kas</h2><table><thead><tr><th>Nomor</th><th>Tanggal</th><th class="num">Seharusnya</th><th class="num">Terhitung</th><th class="num">Selisih</th></tr></thead><tbody>
@forelse ($closings as $c)<tr><td><a href="{{ route('admin.cash.show', $c) }}">{{ $c->number }}</a></td><td>{{ $c->date->format('d/m/Y') }}</td><td class="num">@rp($c->expected)</td><td class="num">@rp($c->counted)</td><td class="num {{ $c->difference < 0 ? 'neg' : '' }}">@rp($c->difference)</td></tr>@empty<tr><td colspan="5" class="muted">Belum pernah tutup kas.</td></tr>@endforelse
</tbody></table></section>
@endsection
