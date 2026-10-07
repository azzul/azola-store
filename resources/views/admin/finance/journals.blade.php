@extends('layouts.admin')
@section('title', 'Jurnal umum')
@section('heading', 'Jurnal umum')
@section('actions')<a class="btn" href="{{ route('admin.adjustment-journals.create') }}">Jurnal manual</a>@endsection
@section('content')
<div class="help">Semua transaksi (penjualan, pembelian, retur, kas, stok, penyusutan) otomatis menjadi jurnal secara realtime. Jurnal tidak bisa diubah atau dihapus; koreksi dilakukan dengan jurnal pembalik.</div>
<x-admin.period :from="$from" :to="$to" :csv="true">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nomor atau keterangan"></div>
    <div><label for="type">Jenis</label><select id="type" name="type"><option value="">Semua</option>@foreach ($types as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>@endforeach</select></div>
</x-admin.period>
<section class="card scroll">
    <table>
        <thead><tr><th>Nomor</th><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead>
        <tbody>
        @forelse ($journals as $j)
            @foreach ($j->lines as $l)
            <tr @class(['sub' => $loop->first])>
                @if ($loop->first)
                <td rowspan="{{ $j->lines->count() }}"><a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a></td><td rowspan="{{ $j->lines->count() }}">{{ $j->date?->format('d/m/Y') }}</td>
                <td rowspan="{{ $j->lines->count() }}">{{ $j->type }}@if ($j->isReversed()) <span class="badge badge--bad">dibalik</span>@endif</td><td rowspan="{{ $j->lines->count() }}">{{ $j->description }}</td>
                @endif
                <td>{{ $l->account->code }} {{ $l->account->name }}</td><td class="num">{{ $l->debit ? \App\Support\Rupiah::format($l->debit) : '' }}</td><td class="num">{{ $l->credit ? \App\Support\Rupiah::format($l->credit) : '' }}</td>
            </tr>
            @endforeach
        @empty
            <tr><td colspan="7" class="muted">Tidak ada jurnal pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $journals->links('pagination.store') }}
</section>
@endsection
