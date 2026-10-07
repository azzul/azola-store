@extends('layouts.admin')
@section('title', 'Retur penjualan')
@section('heading', 'Retur penjualan')
@section('content')
@if (! $order)
<section class="card">
    <form class="filters" method="get"><div><label for="number">Nomor penjualan</label><input id="number" name="number" value="{{ request('number') }}" placeholder="INV-261005-00012" required></div><button class="btn">Cari</button></form>
    <h2>Penjualan terakhir</h2>
    <table><tbody>@foreach ($recent as $o)<tr><td><a href="{{ route('admin.sale-returns.create', ['order' => $o->id]) }}">{{ $o->number }}</a></td><td>{{ $o->customer_name ?: 'Umum' }}</td><td class="num">@rp($o->grand_total)</td></tr>@endforeach</tbody></table>
</section>
@else
<div class="help">Isi jumlah yang diretur per barang. Nilai retur lebih dulu <strong>memotong piutang</strong> faktur ini; sisanya dikembalikan lewat tunai, transfer, atau disimpan sebagai <strong>DP customer</strong>. Hilangkan centang "masuk stok" bila barang rusak.</div>
<form method="post" action="{{ route('admin.sale-returns.store') }}">@csrf
<input type="hidden" name="order_id" value="{{ $order->id }}">
<section class="card"><dl class="kv"><dt>Faktur</dt><dd><a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a> · {{ $order->ordered_at?->format('d/m/Y') }}</dd><dt>Pelanggan</dt><dd>{{ $order->customer_name ?: 'Umum' }}</dd><dt>Total / dibayar / sisa</dt><dd>@rp($order->grand_total) / @rp($order->paid_total) / @rp($order->outstanding())</dd></dl></section>
<section class="card scroll"><table>
    <thead><tr><th>Barang</th><th class="num">Dibeli</th><th class="num">Sudah diretur</th><th>Retur sekarang</th><th>Masuk stok</th></tr></thead><tbody>
    @foreach ($order->items as $i)
        @php($ret = (float) ($returned[$i->id] ?? 0))
        <tr><td>{{ $i->name }}<div class="muted">{{ $i->sku }} · @rp($i->price)</div></td><td class="num">{{ \App\Support\Qty::pretty($i->qty) }}</td><td class="num">{{ \App\Support\Qty::pretty($ret) }}</td>
        <td><input type="number" step="any" min="0" max="{{ max(0, (float) $i->qty - $ret) }}" name="items[{{ $i->id }}][qty]" value="{{ old("items.{$i->id}.qty", 0) }}" aria-label="Retur {{ $i->name }}" style="width:7rem"></td>
        <td><input type="checkbox" name="items[{{ $i->id }}][restock]" value="1" checked aria-label="Masuk stok" style="width:auto"></td></tr>
    @endforeach
</tbody></table></section>
<section class="card"><div class="form-grid form-grid--tight">
    <div class="field"><label for="date">Tanggal retur</label><input id="date" type="date" name="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
    <div class="field"><label for="refund_method">Uang dikembalikan lewat</label><select id="refund_method" name="refund_method"><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="deposit">Simpan sebagai DP</option></select><p class="hint">Dipakai bila nilai retur melebihi piutang faktur.</p></div>
    <div class="field"><label for="reason">Alasan</label><input id="reason" name="reason" value="{{ old('reason') }}" maxlength="200"></div>
</div></section>
<div class="actions"><button class="btn">Simpan retur</button><a class="btn btn--ghost" href="{{ route('admin.sale-returns.create') }}">Batal</a></div>
</form>
@endif
@endsection
