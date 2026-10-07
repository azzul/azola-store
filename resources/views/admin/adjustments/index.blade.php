@extends('layouts.admin')
@section('title', 'Penyesuaian stok')
@section('heading', 'Penyesuaian stok')
@section('actions')<a class="btn" href="{{ route('admin.adjustments.create') }}">Penyesuaian baru</a>@endsection
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true"><div><label for="reason">Alasan</label><select id="reason" name="reason"><option value="">Semua</option>@foreach ($reasons as $k => $v)<option value="{{ $k }}" @selected(request('reason') === $k)>{{ $v }}</option>@endforeach</select></div></x-admin.period>
<div class="kpis"><div class="kpi {{ $total < 0 ? 'kpi--bad' : '' }}"><b>@rp($total)</b><span>Nilai bersih penyesuaian (negatif = kerugian)</span></div></div>
<section class="card scroll"><table><thead><tr><th>Nomor</th><th>Tanggal</th><th>Alasan</th><th>Gudang</th><th class="num">Jenis barang</th><th class="num">Nilai</th></tr></thead><tbody>
@forelse ($adjustments as $a)<tr><td><a href="{{ route('admin.adjustments.show', $a) }}">{{ $a->number }}</a></td><td>{{ $a->date->format('d/m/Y') }}</td><td>{{ $reasons[$a->reason] ?? $a->reason }}</td><td>{{ $a->warehouse?->name }}</td><td class="num">{{ $a->items_count }}</td><td class="num {{ $a->value_total < 0 ? 'neg' : '' }}">@rp($a->value_total)</td></tr>@empty<tr><td colspan="6" class="muted">Belum ada penyesuaian pada periode ini.</td></tr>@endforelse
</tbody></table>{{ $adjustments->links('pagination.store') }}</section>
@endsection
