@extends('layouts.admin')
@section('title', $meta[$stage][0])
@section('heading', 'Order online · '.$meta[$stage][0])
@section('content')
<div class="tabs" role="tablist">
    @foreach (['new' => 'admin.online.new', 'process' => 'admin.online.process', 'shipped' => 'admin.online.shipped', 'done' => 'admin.online.done', 'cancelled' => 'admin.online.cancelled'] as $k => $r)
        <a href="{{ route($r) }}" @class(['is-on' => $stage === $k])>{{ $meta[$k][0] }}@if ($k !== 'done' && $k !== 'cancelled' && ($counts[$k] ?? 0))<span class="navcount">{{ $counts[$k] }}</span>@endif</a>
    @endforeach
</div>
<p class="muted">{{ $meta[$stage][1] }}</p>
<form class="filters" method="get"><div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nomor, nama, telepon, resi"></div><button class="btn btn--ghost">Cari</button></form>
@forelse ($orders as $o)
<section class="card">
    <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div>
            <strong><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a></strong>
            <span class="muted">{{ $o->ordered_at?->format('d/m/Y H:i') }}</span>
            <x-admin.badge :value="$o->payment_status" :map="['unpaid' => ['Belum bayar', 'warn'], 'partial' => ['Sebagian', 'warn'], 'paid' => ['Lunas', 'ok'], 'refunded' => ['Dikembalikan', '']]" />
            <div>{{ $o->customer_name }} · {{ $o->customer_phone }} · {{ $o->delivery_method === 'ship' ? 'Dikirim' : 'Ambil di toko' }}</div>
            @if ($o->customer_address)<div class="muted">{{ $o->customer_address }}</div>@endif
            @if ($o->courier)<div>Kurir: {{ $o->courier }}{{ $o->tracking_no ? ' · resi '.$o->tracking_no : '' }}</div>@endif
        </div>
        <div class="num"><b>@rp($o->grand_total)</b><div class="muted">Sisa @rp($o->outstanding()) · {{ $o->payment_method }}</div></div>
    </div>
    <ul class="muted" style="margin:.5rem 0">@foreach ($o->items as $i)<li>{{ \App\Support\Qty::pretty($i->qty) }} × {{ $i->name }}</li>@endforeach</ul>
    <div class="actions no-print" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
        @if ($o->outstanding() > 0 && $stage !== 'cancelled')
            <form method="post" action="{{ route('admin.orders.pay', $o) }}" style="display:flex;gap:.4rem;align-items:flex-end">@csrf
                <div class="field"><label for="a{{ $o->id }}">Bayar (Rp)</label><input id="a{{ $o->id }}" type="number" name="amount" min="1" max="{{ $o->outstanding() }}" value="{{ $o->outstanding() }}" style="width:9rem"></div>
                <div class="field"><label for="m{{ $o->id }}">Via</label><select id="m{{ $o->id }}" name="method">@foreach ($methods as $m)<option value="{{ $m }}" @selected($m === $o->payment_method)>{{ $m }}</option>@endforeach</select></div>
                <button class="btn btn--ghost btn--sm">Catat bayar</button>
            </form>
        @endif
        @if ($stage === 'new')
            <form method="post" action="{{ route('admin.online.confirm', $o) }}">@csrf<button class="btn btn--sm">Konfirmasi</button></form>
        @endif
        @if (in_array($stage, ['new', 'process'], true))
            <form method="post" action="{{ route('admin.online.ship', $o) }}" style="display:flex;gap:.4rem;align-items:flex-end">@csrf
                <div class="field"><label for="c{{ $o->id }}">Kurir</label><input id="c{{ $o->id }}" name="courier" required maxlength="60" placeholder="JNE / Kurir toko" style="width:9rem"></div>
                <div class="field"><label for="t{{ $o->id }}">No. resi</label><input id="t{{ $o->id }}" name="tracking_no" maxlength="80" style="width:9rem"></div>
                <button class="btn btn--sm">Kirim</button>
            </form>
        @endif
        @if (in_array($stage, ['process', 'shipped'], true))
            <form method="post" action="{{ route('admin.online.complete', $o) }}">@csrf<button class="btn btn--sm">Tandai selesai</button></form>
        @endif
        @if (in_array($stage, ['new', 'process', 'shipped'], true))
            <form method="post" action="{{ route('admin.orders.cancel', $o) }}" onsubmit="return confirm('Batalkan pesanan ini? Stok kembali dan jurnal dibalik.')">@csrf<input type="hidden" name="reason" value="dibatalkan dari order online"><button class="btn btn--danger btn--sm">Batalkan</button></form>
        @endif
    </div>
</section>
@empty
<section class="card"><p class="muted">Tidak ada pesanan pada tahap ini.</p></section>
@endforelse
{{ $orders->links('pagination.store') }}
@endsection
