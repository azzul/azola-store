@extends('layouts.admin')
@section('title', 'Jurnal')
@section('heading', 'Jurnal')
@section('content')
<form class="filters" method="get">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nomor atau keterangan"></div>
    <div><label for="type">Jenis</label><select id="type" name="type"><option value="">Semua</option>@foreach (['sale' => 'Penjualan', 'payment' => 'Pembayaran', 'purchase' => 'Pembelian', 'adjustment' => 'Penyesuaian', 'reversal' => 'Pembalikan'] as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
    <button class="btn btn--ghost">Terapkan</button>
</form>
<section class="card scroll">
    <table>
        <thead><tr><th>Nomor</th><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th class="num">Total</th></tr></thead>
        <tbody>
        @forelse ($journals as $j)
            <tr><td><a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a></td><td>{{ $j->date?->format('d/m/Y') }}</td><td>{{ $j->type }}@if ($j->isReversed()) <span class="badge badge--bad">dibalik</span>@endif</td><td>{{ $j->description }}</td><td class="num">{{ \App\Support\Rupiah::format($j->total) }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada jurnal.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $journals->links('pagination.store') }}
</section>
<p class="muted">Jurnal tidak bisa diubah atau dihapus. Koreksi dilakukan lewat pembatalan pesanan, yang otomatis membuat jurnal balik.</p>
@endsection
