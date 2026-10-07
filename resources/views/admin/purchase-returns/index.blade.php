@extends('layouts.admin')
@section('title', 'Retur pembelian')
@section('heading', 'Retur pembelian')
@section('actions')<a class="btn" href="{{ route('admin.purchase-returns.create') }}">Retur baru</a>@endsection
@section('content')
<x-admin.period :from="$from" :to="$to">
    <div><label for="supplier">Supplier</label><select id="supplier" name="supplier"><option value="">Semua</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
</x-admin.period>
<section class="card scroll">
    <table>
        <thead><tr><th>Nomor</th><th>Tanggal</th><th>Supplier</th><th>Faktur asal</th><th>Penyelesaian</th><th class="num">Total</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($returns as $r)
            <tr>
                <td><a href="{{ route('admin.purchase-returns.show', $r) }}">{{ $r->number }}</a></td>
                <td>{{ $r->date->format('d/m/Y') }}</td>
                <td>{{ $r->supplier?->name }}</td>
                <td>@if ($r->purchase)<a href="{{ route('admin.purchases.show', $r->purchase) }}">{{ $r->purchase->number }}</a>@else - @endif</td>
                <td>{{ ['payable' => 'Potong hutang', 'cash' => 'Tunai', 'bank' => 'Transfer', 'receivable' => 'Piutang supplier'][$r->settlement] }}</td>
                <td class="num">@rp($r->total)</td>
                <td>@if ($r->status === 'cancelled')<span class="badge badge--bad">batal</span>@else<span class="badge badge--ok">sah</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Belum ada retur pembelian pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $returns->links('pagination.store') }}
</section>
@endsection
