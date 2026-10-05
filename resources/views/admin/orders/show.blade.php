@extends('layouts.admin')
@section('title', $order->number)
@section('heading', 'Pesanan '.$order->number)
@section('actions')<a class="btn btn--ghost" href="{{ route('admin.orders.index') }}">Kembali</a>@endsection
@section('content')
@php $cancelled = $order->isCancelled(); @endphp
<div class="grid grid--2">
    <section class="card">
        <h2>Ringkasan</h2>
        <dl class="kv">
            <dt>Waktu</dt><dd>{{ $order->ordered_at?->format('d/m/Y H:i') }}</dd>
            <dt>Kanal</dt><dd>{{ $order->channel }}</dd>
            <dt>Status</dt><dd>{{ $order->status }}</dd>
            <dt>Pembayaran</dt><dd>{{ $order->payment_status }} ({{ $order->payment_method }})</dd>
            <dt>Pelanggan</dt><dd>{{ $order->customer_name ?: 'Umum' }}{{ $order->customer_phone ? ' · '.$order->customer_phone : '' }}</dd>
            @if ($order->customer_address)<dt>Alamat</dt><dd>{{ $order->customer_address }}</dd>@endif
            <dt>Pengambilan</dt><dd>{{ $order->delivery_method === 'ship' ? 'Dikirim' : 'Ambil di toko' }}</dd>
            @if ($order->notes)<dt>Catatan</dt><dd>{{ $order->notes }}</dd>@endif
            <dt>Kasir</dt><dd>{{ $order->user?->name ?? '-' }}</dd>
        </dl>
    </section>

    <section class="card">
        <h2>Tagihan</h2>
        <dl class="kv">
            <dt>Subtotal</dt><dd>{{ \App\Support\Rupiah::format($order->subtotal) }}</dd>
            <dt>Diskon</dt><dd>{{ \App\Support\Rupiah::format($order->discount_total) }}</dd>
            <dt>Pajak</dt><dd>{{ \App\Support\Rupiah::format($order->tax_total) }}</dd>
            <dt>Ongkir</dt><dd>{{ \App\Support\Rupiah::format($order->shipping_fee) }}</dd>
            <dt><b>Total</b></dt><dd><b>{{ \App\Support\Rupiah::format($order->grand_total) }}</b></dd>
            <dt>Sudah dibayar</dt><dd>{{ \App\Support\Rupiah::format($order->paid_total) }}</dd>
            <dt>Sisa</dt><dd>{{ \App\Support\Rupiah::format($order->outstanding()) }}</dd>
            <dt>HPP</dt><dd>{{ \App\Support\Rupiah::format($order->cogs_total) }}</dd>
        </dl>
    </section>
</div>

<section class="card scroll">
    <h2>Barang</h2>
    <table>
        <thead><tr><th>Barang</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
        <tbody>
        @foreach ($order->items as $i)
            <tr><td>{{ $i->name }}<div class="muted">{{ $i->sku }}</div></td><td class="num">{{ \App\Support\Qty::pretty($i->qty) }}</td><td class="num">{{ \App\Support\Rupiah::format($i->price) }}</td><td class="num">{{ \App\Support\Rupiah::format($i->line_total) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</section>

@unless ($cancelled)
<div class="grid grid--2">
    @if ($order->outstanding() > 0)
    <form class="card" method="post" action="{{ route('admin.orders.pay', $order) }}">
        @csrf
        <h2>Catat pembayaran</h2>
        <div class="form-grid">
            <div class="field"><label for="amount">Jumlah (Rp)</label><input id="amount" type="number" name="amount" min="1" max="{{ $order->outstanding() }}" value="{{ $order->outstanding() }}" required></div>
            <div class="field"><label for="method">Metode</label><select id="method" name="method">@foreach ($methods as $m)<option value="{{ $m }}" @selected($m === $order->payment_method)>{{ $m }}</option>@endforeach</select></div>
        </div>
        <button class="btn">Catat pembayaran</button>
        <p class="hint">Jurnal pembayaran terbentuk otomatis dan piutang berkurang.</p>
    </form>
    @endif

    <section class="card">
        <h2>Tindakan</h2>
        @if ($order->status === 'pending')
            <form method="post" action="{{ route('admin.orders.complete', $order) }}" style="margin-bottom:1rem">@csrf<button class="btn btn--ghost">Tandai selesai</button></form>
        @endif
        <form method="post" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm('Batalkan pesanan ini? Stok dikembalikan dan jurnal dibalik.')">
            @csrf
            <div class="field"><label for="reason">Alasan pembatalan</label><input id="reason" type="text" name="reason" maxlength="200"></div>
            <button class="btn btn--danger">Batalkan pesanan</button>
        </form>
    </section>
</div>
@endunless

<section class="card scroll">
    <h2>Jurnal terkait</h2>
    <table>
        <thead><tr><th>Nomor</th><th>Keterangan</th><th class="num">Total</th></tr></thead>
        <tbody>
        @forelse ($order->journals as $j)
            <tr><td><a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a></td><td>{{ $j->description }}</td><td class="num">{{ \App\Support\Rupiah::format($j->total) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">Belum ada jurnal.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
