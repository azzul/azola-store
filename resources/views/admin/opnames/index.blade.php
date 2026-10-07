@extends('layouts.admin')
@section('title', 'Data stok opname')
@section('heading', 'Data stok opname')
@section('actions')<a class="btn" href="{{ route('admin.opnames.create') }}">Opname baru</a>@endsection
@section('content')
<div class="tabs">@foreach ([null => 'Semua', 'draft' => 'Sedang dihitung', 'final' => 'Final'] as $k => $label)<a href="{{ route('admin.opnames.index', array_filter(['status' => $k])) }}" @class(['is-on' => request('status') == $k])>{{ $label }}</a>@endforeach</div>
<section class="card scroll"><table><thead><tr><th>Nomor</th><th>Tanggal</th><th>Gudang</th><th>Progres</th><th>Status</th><th class="num">Selisih nilai</th></tr></thead><tbody>
@forelse ($opnames as $o)<tr><td><a href="{{ route('admin.opnames.show', $o) }}">{{ $o->number }}</a></td><td>{{ $o->date->format('d/m/Y') }}</td><td>{{ $o->warehouse?->name }}</td><td>{{ $o->counted_count }} / {{ $o->items_count }} dihitung</td>
<td>@if ($o->isFinal())<span class="badge badge--ok">final</span>@else<span class="badge badge--warn">draft</span>@endif</td><td class="num {{ $o->value_diff < 0 ? 'neg' : '' }}">{{ $o->isFinal() ? \App\Support\Rupiah::format($o->value_diff) : '-' }}</td></tr>
@empty<tr><td colspan="6" class="muted">Belum ada opname. <a href="{{ route('admin.opnames.create') }}">Mulai hitung stok</a>.</td></tr>@endforelse
</tbody></table>{{ $opnames->links('pagination.store') }}</section>
@endsection
