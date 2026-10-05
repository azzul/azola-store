@extends('layouts.admin')
@section('title', $journal->number)
@section('heading', 'Jurnal '.$journal->number)
@section('actions')<a class="btn btn--ghost" href="{{ route('admin.journals.index') }}">Kembali</a>@endsection
@section('content')
<section class="card">
    <dl class="kv">
        <dt>Tanggal</dt><dd>{{ $journal->date?->format('d/m/Y') }}</dd>
        <dt>Jenis</dt><dd>{{ $journal->type }}</dd>
        <dt>Keterangan</dt><dd>{{ $journal->description }}</dd>
        <dt>Dibuat oleh</dt><dd>{{ $journal->user?->name ?? 'Sistem' }}</dd>
        @if ($journal->source instanceof \App\Models\Order)<dt>Sumber</dt><dd><a href="{{ route('admin.orders.show', $journal->source) }}">Pesanan {{ $journal->source->number }}</a></dd>@endif
        @if ($journal->reversal_of_id)<dt>Membalik</dt><dd><a href="{{ route('admin.journals.show', $journal->reversal_of_id) }}">Jurnal #{{ $journal->reversal_of_id }}</a></dd>@endif
        @if ($journal->reversed_by_id)<dt>Dibalik oleh</dt><dd><a href="{{ route('admin.journals.show', $journal->reversed_by_id) }}">Jurnal #{{ $journal->reversed_by_id }}</a></dd>@endif
    </dl>
</section>
<section class="card scroll">
    <table>
        <thead><tr><th>Akun</th><th>Memo</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead>
        <tbody>
        @foreach ($journal->lines as $l)
            <tr><td>{{ $l->account->code }} {{ $l->account->name }}</td><td>{{ $l->memo }}</td><td class="num">{{ $l->debit ? \App\Support\Rupiah::format($l->debit) : '' }}</td><td class="num">{{ $l->credit ? \App\Support\Rupiah::format($l->credit) : '' }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="num">{{ \App\Support\Rupiah::format($journal->lines->sum('debit')) }}</td><td class="num">{{ \App\Support\Rupiah::format($journal->lines->sum('credit')) }}</td></tr></tfoot>
    </table>
</section>
@endsection
