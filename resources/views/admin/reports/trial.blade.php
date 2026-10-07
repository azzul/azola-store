@extends('layouts.admin')
@section('title', 'Neraca saldo')
@section('heading', 'Neraca saldo')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="true" />
<section class="card scroll"><table>
    <thead><tr><th>Kode</th><th>Akun</th><th class="num">Saldo awal</th><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Saldo akhir</th></tr></thead><tbody>
    @forelse ($trial['rows'] as $r)<tr><td>{{ $r['account']->code }}</td><td><a href="{{ route('admin.reports.ledger', ['account' => $r['account']->id, 'from' => $from, 'to' => $to]) }}">{{ $r['account']->name }}</a></td><td class="num">@rp($r['opening'])</td><td class="num">@rp($r['debit'])</td><td class="num">@rp($r['credit'])</td><td class="num">@rp($r['closing'])</td></tr>@empty<tr><td colspan="6" class="muted">Belum ada transaksi.</td></tr>@endforelse
</tbody><tfoot><tr><td colspan="3">Total {{ $trial['debit'] === $trial['credit'] ? '(seimbang)' : '(TIDAK seimbang)' }}</td><td class="num">@rp($trial['debit'])</td><td class="num">@rp($trial['credit'])</td><td></td></tr></tfoot></table></section>
@endsection
