@extends('layouts.admin')
@section('title', $customer->name)
@section('heading', $customer->name)
@section('actions')
    <a class="btn" href="{{ route('admin.sales.create', ['customer' => $customer->id]) }}">Input penjualan</a>
    <a class="btn btn--ghost" href="{{ route('admin.deposits.create', ['customer' => $customer->id]) }}">Terima DP</a>
    <a class="btn btn--ghost" href="{{ route('admin.customers.edit', $customer) }}">Ubah</a>
@endsection
@section('content')
<div class="kpis">
    <div class="kpi {{ $customer->receivable() > 0 ? 'kpi--warn' : '' }}"><b>@rp($customer->receivable())</b><span>Piutang (belum dibayar)</span></div>
    <div class="kpi"><b>@rp($customer->depositBalance())</b><span>Saldo DP</span></div>
    <div class="kpi"><b>{{ $customer->priceLevel?->name ?? 'Ecer' }}</b><span>Level harga</span></div>
</div>
<div class="grid grid--2">
    <section class="card"><h2>Data</h2>
        <dl class="kv"><dt>Telepon</dt><dd>{{ $customer->phone ?: '-' }}</dd><dt>Email</dt><dd>{{ $customer->email ?: '-' }}</dd><dt>Alamat</dt><dd>{{ $customer->address ?: '-' }}</dd><dt>Batas piutang</dt><dd>@rp($customer->credit_limit)</dd><dt>Catatan</dt><dd>{{ $customer->note ?: '-' }}</dd></dl>
        <p class="actions no-print"><a href="{{ route('admin.cards.receivable', ['customer' => $customer->id]) }}">Kartu piutang</a></p>
    </section>
    <section class="card scroll"><h2>DP / saldo titipan</h2>
        <table><thead><tr><th>Nomor</th><th>Jenis</th><th class="num">Saldo</th></tr></thead><tbody>
        @forelse ($deposits as $d)<tr><td><a href="{{ route('admin.deposits.show', $d) }}">{{ $d->number }}</a><div class="muted">{{ $d->date->format('d/m/Y') }}</div></td><td>{{ ['dp' => 'DP', 'overpay' => 'Lebih transfer', 'return' => 'Dari retur'][$d->kind] ?? $d->kind }}</td><td class="num">@rp($d->balance())</td></tr>
        @empty<tr><td colspan="3" class="muted">Belum ada.</td></tr>@endforelse
        </tbody></table>
    </section>
</div>
<section class="card scroll"><h2>Pesanan terakhir</h2>
    <table><thead><tr><th>Nomor</th><th>Tanggal</th><th>Status</th><th class="num">Total</th><th class="num">Sisa tagihan</th></tr></thead><tbody>
    @forelse ($orders as $o)<tr><td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a></td><td>{{ $o->ordered_at?->format('d/m/Y H:i') }}</td><td>{{ $o->status }}</td><td class="num">@rp($o->grand_total)</td><td class="num">@rp($o->status === 'cancelled' ? 0 : $o->outstanding())</td></tr>
    @empty<tr><td colspan="5" class="muted">Belum ada pesanan.</td></tr>@endforelse
    </tbody></table>
</section>
@endsection
