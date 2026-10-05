@php
    $channels = ['web' => 'Web', 'pos_desktop' => 'Pos Desktop', 'pos_android' => 'Pos Android', 'admin' => 'Admin'];
    $statuses = ['pending' => ['Menunggu', 'warn'], 'completed' => ['Selesai', 'ok'], 'cancelled' => ['Batal', 'bad']];
    $pays = ['unpaid' => ['Belum bayar', 'warn'], 'partial' => ['Sebagian', 'warn'], 'paid' => ['Lunas', 'ok'], 'refunded' => ['Dikembalikan', '']];
@endphp
<div class="scroll">
<table>
    <thead><tr><th>Nomor</th><th>Waktu</th><th>Pelanggan</th><th>Kanal</th><th>Status</th><th>Pembayaran</th><th class="num">Total</th></tr></thead>
    <tbody>
    @forelse ($orders as $o)
        <tr>
            <td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a></td>
            <td>{{ $o->ordered_at?->format('d/m/Y H:i') }}</td>
            <td>{{ $o->customer_name ?: 'Umum' }}</td>
            <td>{{ $channels[$o->channel] ?? $o->channel }}</td>
            <td><span class="badge badge--{{ $statuses[$o->status][1] ?? '' }}">{{ $statuses[$o->status][0] ?? $o->status }}</span></td>
            <td><span class="badge badge--{{ $pays[$o->payment_status][1] ?? '' }}">{{ $pays[$o->payment_status][0] ?? $o->payment_status }}</span></td>
            <td class="num">{{ \App\Support\Rupiah::format($o->grand_total) }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">Belum ada pesanan.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
