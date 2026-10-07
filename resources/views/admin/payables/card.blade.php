@extends('layouts.admin')
@section('title', 'Kartu hutang')
@section('heading', 'Kartu hutang')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="(bool) $supplier">
    <div><label for="supplier">Supplier</label><select id="supplier" name="supplier" required><option value="">Pilih supplier</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($supplier?->id === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
</x-admin.period>
@if ($card)
<section class="card">
    <h2 class="report-title">{{ $supplier->name }}</h2>
    <p class="report-sub">Periode {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}. Berasal dari jurnal Utang Usaha, jadi selalu cocok dengan buku besar.</p>
    @include('admin.partials.subledger', ['card' => $card, 'plus' => 'Hutang bertambah', 'minus' => 'Dibayar / dipotong', 'side' => 'credit'])
</section>
@else
<p class="muted">Pilih supplier untuk melihat kartu hutangnya.</p>
@endif
@endsection
