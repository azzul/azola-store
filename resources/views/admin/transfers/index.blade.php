@extends('layouts.admin')
@section('title', 'Alih gudang')
@section('heading', 'Alih gudang & penerimaan')
@section('actions')<a class="btn" href="{{ route('admin.transfers.create') }}">Kirim barang</a>@endsection
@section('content')
<div class="tabs" role="tablist">
    @foreach (['sent' => 'Dalam perjalanan', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan', 'all' => 'Semua'] as $k => $label)
        <a href="{{ route('admin.transfers.index', ['status' => $k]) }}" @class(['is-on' => $status === $k])>{{ $label }}@if ($k !== 'all' && ($counts[$k] ?? 0))<span class="navcount">{{ $counts[$k] }}</span>@endif</a>
    @endforeach
</div>
<section class="card scroll">
    <table>
        <thead><tr><th>Nomor</th><th>Tanggal</th><th>Dari</th><th>Ke</th><th class="num">Jenis barang</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($transfers as $t)
            <tr>
                <td><a href="{{ route('admin.transfers.show', $t) }}">{{ $t->number }}</a></td>
                <td>{{ $t->date->format('d/m/Y') }}</td><td>{{ $t->from->name }}</td><td>{{ $t->to->name }}</td>
                <td class="num">{{ $t->items_count }}</td>
                <td><x-admin.badge :value="$t->status" :map="['sent' => ['Dalam perjalanan', 'warn'], 'received' => ['Diterima', 'ok'], 'cancelled' => ['Batal', 'bad']]" /></td>
                <td>@if ($t->status === 'sent')<a href="{{ route('admin.transfers.show', $t) }}">Terima</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Tidak ada data. <a href="{{ route('admin.transfers.create') }}">Kirim barang antar gudang</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $transfers->links('pagination.store') }}
</section>
@endsection
