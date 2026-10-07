@extends('layouts.admin')
@section('title', 'Pembayaran hutang')
@section('heading', 'Pembayaran hutang')
@section('actions')<a class="btn" href="{{ route('admin.supplier-payments.create') }}">Bayar hutang</a>@endsection
@section('content')
<x-admin.period :from="$from" :to="$to">
    <div><label for="supplier">Supplier</label><select id="supplier" name="supplier"><option value="">Semua</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
</x-admin.period>
<div class="kpis"><div class="kpi"><b>@rp($total)</b><span>Total dibayar pada periode ini</span></div></div>
<section class="card scroll">
    <table><thead><tr><th>Nomor</th><th>Tanggal</th><th>Supplier</th><th>Lewat</th><th class="num">Jumlah</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse ($payments as $p)
        <tr><td>{{ $p->number }}@if ($p->note)<div class="muted">{{ $p->note }}</div>@endif</td><td>{{ $p->date->format('d/m/Y') }}</td><td><a href="{{ route('admin.suppliers.show', $p->supplier) }}">{{ $p->supplier->name }}</a></td>
        <td>{{ $p->method === 'cash' ? 'Kas' : 'Bank' }}</td><td class="num">@rp($p->amount)</td>
        <td>@if ($p->status === 'cancelled')<span class="badge badge--bad">batal</span>@else<span class="badge badge--ok">sah</span>@endif</td>
        <td class="no-print">@if ($p->status === 'posted')<form method="post" action="{{ route('admin.supplier-payments.cancel', $p) }}" onsubmit="return confirm('Batalkan pembayaran ini? Hutang kembali dan jurnal dibalik.')">@csrf<button class="btn btn--ghost btn--sm">Batalkan</button></form>@endif</td></tr>
    @empty<tr><td colspan="7" class="muted">Belum ada pembayaran hutang pada periode ini.</td></tr>@endforelse
    </tbody></table>
    {{ $payments->links('pagination.store') }}
</section>
@endsection
