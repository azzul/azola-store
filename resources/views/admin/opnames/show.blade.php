@extends('layouts.admin')
@section('title', $opname->number)
@section('heading', 'Opname '.$opname->number)
@section('actions')<button class="btn btn--ghost" type="button" onclick="window.print()">Cetak lembar hitung</button>@endsection
@section('content')
@php($final = $opname->isFinal())
<section class="card"><dl class="kv"><dt>Tanggal</dt><dd>{{ $opname->date->format('d/m/Y') }}</dd><dt>Gudang</dt><dd>{{ $opname->warehouse?->name }}</dd><dt>Status</dt><dd>@if ($final)<span class="badge badge--ok">final</span> {{ $opname->finalized_at?->format('d/m/Y H:i') }}@else<span class="badge badge--warn">draft</span>@endif</dd>
@if ($final)<dt>Selisih nilai</dt><dd class="{{ $opname->value_diff < 0 ? 'neg' : '' }}">@rp($opname->value_diff)@foreach ($opname->journals as $j) · <a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a>@endforeach</dd>@endif
@if ($opname->note)<dt>Catatan</dt><dd>{{ $opname->note }}</dd>@endif</dl></section>
<div class="tabs no-print">@foreach ([null => 'Semua', 'todo' => 'Belum dihitung', 'diff' => 'Ada selisih'] as $k => $label)<a href="{{ route('admin.opnames.show', array_filter(['opname' => $opname->id, 'tampil' => $k])) }}" @class(['is-on' => $filter == $k])>{{ $label }}</a>@endforeach</div>
<form method="post" action="{{ route('admin.opnames.save', $opname) }}">@csrf
<section class="card scroll"><table>
    <thead><tr><th>Barang</th><th class="num">Stok sistem</th><th>Hasil hitung</th><th class="num">Selisih</th><th class="num">Nilai selisih</th><th>Catatan</th></tr></thead><tbody>
    @forelse ($items as $i)
        @php($diff = $i->counted_qty === null ? null : \App\Support\Qty::toMilli($i->counted_qty) - \App\Support\Qty::toMilli($i->system_qty))
        <tr><td>{{ $i->product?->name }}<div class="muted">{{ $i->product?->sku }} · {{ $i->product?->unit }}</div></td><td class="num">{{ \App\Support\Qty::pretty($i->system_qty) }}</td>
        <td>@if ($final){{ $i->counted_qty === null ? '-' : \App\Support\Qty::pretty($i->counted_qty) }}@else<input type="number" step="any" min="0" name="counts[{{ $i->id }}]" value="{{ $i->counted_qty === null ? '' : (float) $i->counted_qty }}" aria-label="Hitung {{ $i->product?->name }}" style="width:8rem">@endif</td>
        <td class="num {{ $diff < 0 ? 'neg' : '' }}">{{ $diff === null ? '' : (($diff > 0 ? '+' : '').\App\Support\Qty::pretty($diff / 1000)) }}</td>
        <td class="num">{{ $diff === null || $diff === 0 ? '' : \App\Support\Rupiah::format(\App\Support\Qty::value($diff, (int) $i->unit_cost)) }}</td>
        <td>@if ($final){{ $i->note }}@else<input name="notes[{{ $i->id }}]" value="{{ $i->note }}" maxlength="200" aria-label="Catatan {{ $i->product?->name }}">@endif</td></tr>
    @empty<tr><td colspan="6" class="muted">Tidak ada barang pada tampilan ini.</td></tr>@endforelse
</tbody></table></section>
@unless ($final)
<div class="actions no-print"><button class="btn btn--ghost">Simpan hasil hitung</button><button class="btn" name="finalize" value="1" onclick="return confirm('Finalkan opname? Selisih akan dibukukan dan tidak bisa diubah lagi.')">Simpan & finalkan</button></div>
</form>
<form class="no-print" method="post" action="{{ route('admin.opnames.destroy', $opname) }}" onsubmit="return confirm('Hapus sesi opname ini?')" style="margin-top:1rem">@csrf @method('DELETE')<button class="btn btn--danger btn--sm">Hapus sesi draft</button></form>
@else
</form>
@endunless
@endsection
