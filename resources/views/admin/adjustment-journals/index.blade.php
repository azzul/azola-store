@extends('layouts.admin')
@section('title', 'Jurnal penyesuaian')
@section('heading', 'Jurnal penyesuaian')
@section('actions')<a class="btn" href="{{ route('admin.adjustment-journals.create') }}">Jurnal manual</a>@endsection
@section('content')
<div class="help">Berisi jurnal manual buatan Anda serta jurnal penyesuaian otomatis dari sistem: penyesuaian & opname stok, selisih tutup kas, dan penyusutan aset.</div>
<x-admin.period :from="$from" :to="$to"><div><label for="type">Jenis</label><select id="type" name="type"><option value="">Semua</option>@foreach (['manual' => 'Manual', 'adjustment' => 'Penyesuaian stok', 'depreciation' => 'Penyusutan', 'cash_close' => 'Selisih kas'] as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div></x-admin.period>
<section class="card scroll"><table><thead><tr><th>Nomor</th><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th class="num">Total</th></tr></thead><tbody>
@forelse ($journals as $j)<tr><td><a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a></td><td>{{ $j->date->format('d/m/Y') }}</td><td>{{ $j->type }}@if ($j->isReversed()) <span class="badge badge--bad">dibalik</span>@endif</td><td>{{ $j->description }}</td><td class="num">@rp($j->total)</td></tr>@empty<tr><td colspan="5" class="muted">Tidak ada jurnal penyesuaian pada periode ini.</td></tr>@endforelse
</tbody></table>{{ $journals->links('pagination.store') }}</section>
@endsection
