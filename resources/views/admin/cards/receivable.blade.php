@extends('layouts.admin')
@section('title', 'Kartu piutang')
@section('heading', 'Kartu piutang customer')
@section('content')
<x-admin.period :from="$from" :to="$to" :csv="(bool) $customer">
    <div><label for="customer">Customer</label><select id="customer" name="customer" required><option value="">Pilih customer</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($customer?->id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
</x-admin.period>
@if ($card)
<div class="kpis"><div class="kpi {{ $customer->receivable() > 0 ? 'kpi--warn' : '' }}"><b>@rp($customer->receivable())</b><span>Piutang {{ $customer->name }} saat ini</span></div><div class="kpi"><b>@rp($customer->depositBalance())</b><span>Saldo DP</span></div></div>
<section class="card"><h2>Kartu piutang</h2>@include('admin.partials.subledger', ['card' => $card, 'plus' => 'Piutang naik', 'minus' => 'Dibayar / diretur', 'side' => 'debit'])</section>
@if ($open->isNotEmpty())
<section class="card scroll"><h2>Faktur belum lunas</h2><table><thead><tr><th>Faktur</th><th>Jatuh tempo</th><th class="num">Sisa</th><th></th></tr></thead><tbody>
@foreach ($open as $o)<tr><td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a></td><td>{{ $o->due_date?->format('d/m/Y') ?: '-' }}</td><td class="num">@rp($o->outstanding())</td><td class="no-print"><a href="{{ route('admin.orders.show', $o) }}">Catat pembayaran</a></td></tr>@endforeach
</tbody></table></section>
@endif
<section class="card"><h2>Kartu DP</h2>@include('admin.partials.subledger', ['card' => $deposit, 'plus' => 'DP masuk', 'minus' => 'DP terpakai / kembali', 'side' => 'credit'])</section>
@else
<p class="muted">Pilih customer untuk melihat kartu piutang dan DP-nya.</p>
@endif
@endsection
