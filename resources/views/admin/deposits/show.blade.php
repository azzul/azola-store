@extends('layouts.admin')
@section('title', $deposit->number)
@section('heading', 'DP '.$deposit->number)
@section('content')
<div class="grid grid--2">
<section class="card"><dl class="kv"><dt>Customer</dt><dd>@if ($deposit->customer)<a href="{{ route('admin.customers.show', $deposit->customer) }}">{{ $deposit->customer->name }}</a>@else{{ $deposit->customer_name ?? '-' }}@endif</dd>
<dt>Tanggal</dt><dd>{{ $deposit->date->format('d/m/Y') }}</dd><dt>Jenis</dt><dd>{{ ['dp' => 'DP', 'overpay' => 'Kelebihan transfer', 'return' => 'Dari retur'][$deposit->kind] ?? $deposit->kind }}</dd>
@if ($deposit->order)<dt>Faktur</dt><dd><a href="{{ route('admin.orders.show', $deposit->order) }}">{{ $deposit->order->number }}</a></dd>@endif<dt>Catatan</dt><dd>{{ $deposit->note ?: '-' }}</dd></dl></section>
<section class="card"><dl class="kv"><dt>Jumlah</dt><dd>@rp($deposit->amount)</dd><dt>Terpakai</dt><dd>@rp($deposit->used_total)</dd><dt>Dikembalikan</dt><dd>@rp($deposit->refunded_total)</dd><dt><b>Saldo</b></dt><dd><b>@rp($deposit->balance())</b></dd></dl></section>
</div>
@if ($deposit->balance() > 0)
<div class="grid grid--2">
<form class="card" method="post" action="{{ route('admin.deposits.apply', $deposit) }}">@csrf<h2>Pakai untuk melunasi pesanan</h2>
    @if ($orders->isEmpty())<p class="muted">Customer ini tidak punya pesanan yang belum lunas.</p>@else
    <div class="field"><label for="order_id">Pesanan</label><select id="order_id" name="order_id">@foreach ($orders as $o)<option value="{{ $o->id }}">{{ $o->number }} · sisa {{ \App\Support\Rupiah::format($o->outstanding()) }}</option>@endforeach</select></div>
    <div class="field"><label for="a1">Jumlah (Rp)</label><input id="a1" type="number" name="amount" min="1" max="{{ $deposit->balance() }}" value="{{ min($deposit->balance(), $orders->first()->outstanding()) }}" required></div>
    <button class="btn">Pakai DP</button>@endif
</form>
<form class="card" method="post" action="{{ route('admin.deposits.refund', $deposit) }}" onsubmit="return confirm('Kembalikan DP ke customer?')">@csrf<h2>Kembalikan ke customer</h2>
    <div class="field"><label for="a2">Jumlah (Rp)</label><input id="a2" type="number" name="amount" min="1" max="{{ $deposit->balance() }}" value="{{ $deposit->balance() }}" required></div>
    <div class="field"><label for="m2">Lewat</label><select id="m2" name="method"><option value="cash">Tunai</option><option value="transfer">Transfer</option></select></div>
    <button class="btn btn--ghost">Kembalikan</button>
</form>
</div>
@endif
<section class="card scroll"><h2>Jurnal</h2><table><thead><tr><th>Jurnal</th><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead><tbody>
@foreach ($deposit->journals as $j)@foreach ($j->lines as $line)<tr><td>@if ($loop->first)<a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a>@endif</td><td>{{ $line->account->code }} {{ $line->account->name }}</td><td class="num">{{ $line->debit ? \App\Support\Rupiah::format($line->debit) : '' }}</td><td class="num">{{ $line->credit ? \App\Support\Rupiah::format($line->credit) : '' }}</td></tr>@endforeach @endforeach
</tbody></table></section>
@endsection
