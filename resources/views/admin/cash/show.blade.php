@extends('layouts.admin')
@section('title', $closing->number)
@section('heading', 'Tutup kas '.$closing->number)
@section('actions')<button class="btn btn--ghost" type="button" onclick="window.print()">Cetak</button>@endsection
@section('content')
<div class="grid grid--2">
<section class="card"><dl class="kv"><dt>Tanggal</dt><dd>{{ $closing->date->format('d/m/Y') }}</dd><dt>Kas awal</dt><dd>@rp($closing->opening)</dd><dt>Masuk</dt><dd>@rp($closing->cash_in)</dd><dt>Keluar</dt><dd>@rp($closing->cash_out)</dd><dt>Seharusnya</dt><dd>@rp($closing->expected)</dd><dt>Terhitung</dt><dd>@rp($closing->counted)</dd><dt><b>Selisih</b></dt><dd><b class="{{ $closing->difference < 0 ? 'neg' : '' }}">@rp($closing->difference)</b></dd><dt>Petugas</dt><dd>{{ $closing->user?->name ?? '-' }}</dd><dt>Catatan</dt><dd>{{ $closing->note ?: '-' }}</dd></dl></section>
<section class="card scroll"><h2>Pecahan</h2><table><thead><tr><th>Pecahan</th><th class="num">Jumlah</th><th class="num">Nilai</th></tr></thead><tbody>
@foreach ($denominations as $v)@php($n = (int) ($closing->denominations[$v] ?? 0))@if ($n)<tr><td>@rp($v)</td><td class="num">{{ $n }}</td><td class="num">@rp($v * $n)</td></tr>@endif @endforeach
</tbody></table></section>
</div>
<section class="card scroll"><h2>Jurnal</h2>@forelse ($closing->journals as $j)<p><a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a> {{ $j->description }}</p>@empty<p class="muted">Tidak ada selisih, jadi tidak ada jurnal.</p>@endforelse</section>
@endsection
