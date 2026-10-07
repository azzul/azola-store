@php
    $status = fn ($o) => match (true) {
        $o->isCancelled() => ['Dibatalkan', 'off'],
        $o->payment_status === 'paid' => ['Lunas', 'ok'],
        $o->payment_status === 'partial' => ['Dibayar sebagian', 'wait'],
        default => ['Menunggu pembayaran', 'wait'],
    };
@endphp
@forelse ($orders as $order)
    @php [$label, $tone] = $status($order); @endphp
    <a class="ordercard" href="{{ route('order.show', $order->uuid) }}">
        <span class="ordercard__top"><strong>{{ $order->number }}</strong><span class="status status--{{ $tone }}">{{ $label }}</span></span>
        <span class="muted">{{ $order->ordered_at->translatedFormat('d M Y, H:i') }} &middot; {{ $order->delivery_method === 'ship' ? 'Dikirim' : 'Ambil di toko' }}</span>
        <span class="ordercard__total">{{ \App\Support\Rupiah::format($order->grand_total) }}</span>
    </a>
@empty
    <div class="empty">
        <p><strong>Belum ada pesanan.</strong></p>
        <p class="muted">Pesanan yang kamu buat saat masuk ke akun akan muncul di sini.</p>
        <a class="btn" href="{{ route('shop.index') }}">Mulai belanja</a>
    </div>
@endforelse
