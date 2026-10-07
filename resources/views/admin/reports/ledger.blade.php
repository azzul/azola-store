@extends('layouts.admin')
@section('title', 'General ledger')
@section('heading', 'General ledger (buku besar)')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="(bool) $account">
    <div><label for="account">Akun</label><select id="account" name="account" required><option value="">Pilih akun</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($account?->id === $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></div>
</x-admin.period>
@if ($ledger)
<section class="card scroll">
    <h2 class="report-title">{{ $account->code }} {{ $account->name }}</h2>
    <p class="report-sub">Sisi normal: {{ $account->normal_balance === 'debit' ? 'debit' : 'kredit' }} · periode {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</p>
    <table><thead><tr><th>Tanggal</th><th>Jurnal</th><th>Keterangan</th><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Saldo</th></tr></thead><tbody>
        <tr class="sub"><td colspan="5">Saldo awal</td><td class="num">@rp($ledger['opening'])</td></tr>
        @forelse ($ledger['rows'] as $r)@php($l = $r['line'])<tr><td>{{ \Carbon\Carbon::parse($l->date)->format('d/m/Y') }}</td><td><a href="{{ route('admin.journals.show', $l->journal_id) }}">{{ $l->number }}</a></td><td>{{ $l->description }}@if ($l->memo)<div class="muted">{{ $l->memo }}</div>@endif</td><td class="num">{{ $l->debit ? \App\Support\Rupiah::format($l->debit) : '' }}</td><td class="num">{{ $l->credit ? \App\Support\Rupiah::format($l->credit) : '' }}</td><td class="num">@rp($r['balance'])</td></tr>
        @empty<tr><td colspan="6" class="muted">Tidak ada transaksi pada periode ini.</td></tr>@endforelse
    </tbody><tfoot><tr><td colspan="3">Total mutasi · saldo akhir</td><td class="num">@rp($ledger['debit'])</td><td class="num">@rp($ledger['credit'])</td><td class="num">@rp($ledger['closing'])</td></tr></tfoot></table>
</section>
@else<p class="muted">Pilih akun untuk melihat buku besarnya.</p>@endif
@endsection
