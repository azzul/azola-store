@extends('layouts.store')

@section('content')
    @php
        $statusText = match (true) {
            $order->isCancelled() => 'Pesanan dibatalkan',
            $order->payment_status === 'paid' => 'Pembayaran diterima',
            default => 'Menunggu pembayaran',
        };
        $wa = preg_replace('/\D/', '', config('store.whatsapp'));
    @endphp

    <div class="wrap page page--narrow">
        <h1 class="page__title">Pesanan {{ $order->number }}</h1>
        <p class="status status--{{ $order->isCancelled() ? 'off' : ($order->payment_status === 'paid' ? 'ok' : 'wait') }}">{{ $statusText }}</p>

        @unless ($order->isCancelled() || $order->payment_status === 'paid')
            <section class="pay">
                <h2>Cara membayar</h2>
                @if ($order->payment_method === 'transfer')
                    <p>Transfer <strong>{{ \App\Support\Rupiah::format($order->outstanding()) }}</strong> ke:</p>
                    <p class="pay__bank">{{ config('store.bank.name') }} {{ config('store.bank.account') }}<br><span class="muted">a.n. {{ config('store.bank.holder') }}</span></p>
                    <p>Setelah transfer, kirim bukti pembayaran lewat WhatsApp supaya pesananmu segera diproses.</p>
                @elseif ($order->payment_method === 'qris')
                    <p>Toko akan mengirim kode QRIS senilai <strong>{{ \App\Support\Rupiah::format($order->outstanding()) }}</strong> lewat WhatsApp.</p>
                @else
                    <p>Siapkan <strong>{{ \App\Support\Rupiah::format($order->outstanding()) }}</strong> saat barang diterima atau diambil.</p>
                @endif
                @if ($wa)
                    <p><a class="btn" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Halo, saya mau konfirmasi pesanan '.$order->number) }}" rel="noopener">Konfirmasi lewat WhatsApp</a></p>
                @endif
            </section>
        @endunless

        <table class="table">
            <thead><tr><th>Barang</th><th class="num">Jumlah</th><th class="num">Subtotal</th></tr></thead>
            <tbody>
            @foreach ($order->items as $item)
                <tr><td>{{ $item->name }}</td><td class="num">{{ \App\Support\Qty::pretty($item->qty) }}</td><td class="num">{{ \App\Support\Rupiah::format($item->line_total) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot>
                @if ($order->shipping_fee > 0)<tr><td colspan="2">Ongkos kirim</td><td class="num">{{ \App\Support\Rupiah::format($order->shipping_fee) }}</td></tr>@endif
                @if ($order->tax_total > 0)<tr><td colspan="2">Pajak</td><td class="num">{{ \App\Support\Rupiah::format($order->tax_total) }}</td></tr>@endif
                <tr class="table__grand"><td colspan="2">Total</td><td class="num">{{ \App\Support\Rupiah::format($order->grand_total) }}</td></tr>
            </tfoot>
        </table>

        <dl class="facts">
            <div><dt>Atas nama</dt><dd>{{ $order->customer_name }}</dd></div>
            <div><dt>Pengambilan</dt><dd>{{ $order->delivery_method === 'ship' ? 'Dikirim ke '.$order->customer_address : 'Ambil di toko' }}</dd></div>
            <div><dt>Pembayaran</dt><dd>{{ $methods[$order->payment_method] ?? $order->payment_method }}</dd></div>
        </dl>

        <p class="muted">Simpan halaman ini. Alamat halaman ini adalah kunci untuk melihat status pesananmu.</p>
        <p><a href="{{ route('shop.index') }}">Lanjut belanja</a></p>
    </div>
@endsection
