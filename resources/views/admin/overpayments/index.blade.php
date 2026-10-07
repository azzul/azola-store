@extends('layouts.admin')
@section('title', 'Kembalian lebih transfer')
@section('heading', 'Kembalian lebih transfer')
@section('content')
<div class="help">Pelanggan transfer lebih besar dari tagihan? Catat kelebihannya di sini. Uang tidak dianggap pendapatan: dicatat sebagai <strong>DP customer</strong> (kewajiban), lalu dikembalikan lewat transfer atau disimpan untuk belanja berikutnya.</div>
<div class="grid grid--2">
<section class="card"><h2>Catat kelebihan</h2>
    @if (! $order)
        <form class="filters" method="get"><div><label for="number">Nomor penjualan</label><input id="number" name="number" placeholder="INV-261005-00012" required></div><button class="btn">Cari</button></form>
    @else
        <form method="post" action="{{ route('admin.overpayments.store') }}">@csrf
            <input type="hidden" name="order_id" value="{{ $order->id }}">
            <p><a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a> · {{ $order->customer_name ?: 'Umum' }} · total @rp($order->grand_total)</p>
            <div class="field"><label for="amount">Kelebihan transfer (Rp)</label><input id="amount" type="number" min="1" name="amount" required></div>
            <div class="field"><label for="refund">Penyelesaian</label><select id="refund" name="refund"><option value="now">Kembalikan sekarang (transfer)</option><option value="keep">Simpan sebagai DP</option></select></div>
            <button class="btn">Catat</button> <a class="btn btn--ghost" href="{{ route('admin.overpayments.index') }}">Ganti nomor</a>
        </form>
    @endif
</section>
<section class="card scroll"><h2>Riwayat</h2><table><thead><tr><th>Nomor</th><th>Tanggal</th><th>Faktur</th><th class="num">Jumlah</th><th class="num">Saldo</th></tr></thead><tbody>
    @forelse ($rows as $d)<tr><td><a href="{{ route('admin.deposits.show', $d) }}">{{ $d->number }}</a></td><td>{{ $d->date->format('d/m/Y') }}</td><td>{{ $d->order?->number }}</td><td class="num">@rp($d->amount)</td><td class="num">@rp($d->balance())</td></tr>@empty<tr><td colspan="5" class="muted">Belum ada.</td></tr>@endforelse
</tbody></table>{{ $rows->links('pagination.store') }}</section>
</div>
@endsection
