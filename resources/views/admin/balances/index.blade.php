@extends('layouts.admin')
@section('title', 'Mutasi saldo')
@section('heading', 'Mutasi saldo')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true">
    <div><label for="type">Kelompok akun</label><select id="type" name="type"><option value="">Aset, kewajiban, modal</option>@foreach ($types as $k => $v)<option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>@endforeach</select></div>
</x-admin.period>
<section class="card scroll"><table>
    <thead><tr><th>Kode</th><th>Akun</th><th class="num">Saldo awal</th><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Saldo akhir</th></tr></thead><tbody>
    @forelse ($trial['rows'] as $r)
        <tr><td>{{ $r['account']->code }}</td><td><a href="{{ route('admin.reports.ledger', ['account' => $r['account']->id, 'from' => $from, 'to' => $to]) }}">{{ $r['account']->name }}</a></td><td class="num">@rp($r['opening'])</td><td class="num">@rp($r['debit'])</td><td class="num">@rp($r['credit'])</td><td class="num"><b>@rp($r['closing'])</b></td></tr>
    @empty<tr><td colspan="6" class="muted">Tidak ada mutasi.</td></tr>@endforelse
</tbody><tfoot><tr><td colspan="3">Total mutasi</td><td class="num">@rp($trial['debit'])</td><td class="num">@rp($trial['credit'])</td><td></td></tr></tfoot></table></section>
@endsection
