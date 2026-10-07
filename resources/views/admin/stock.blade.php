@extends('layouts.admin')
@section('title', 'Stok barang terkini')
@section('heading', 'Stok barang terkini')
@section('actions')<span class="live" data-live><i></i> Tersambung, diperbarui otomatis</span>@endsection
@section('content')
<form class="filters" method="get">
    <div><label for="q">Cari produk</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama, SKU, atau barcode"></div>
    <div><label for="tampil">Tampilkan</label>
        <select id="tampil" name="tampil"><option value="">Semua</option><option value="low" @selected($filter === 'low')>Menipis</option><option value="out" @selected($filter === 'out')>Habis</option></select></div>
    <button class="btn btn--ghost">Terapkan</button>
</form>

<div class="grid grid--wide">
    <section class="card scroll">
        <table>
            <thead><tr><th>Produk</th><th class="num">Stok toko</th><th class="num">Gudang lain / perjalanan</th><th class="num">Total</th><th>Status</th><th class="num">Nilai (HPP)</th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                <tr data-stock-id="{{ $p->id }}" data-state="{{ $p->stockState() }}">
                    <td><a href="{{ route('admin.products.edit', $p) }}">{{ $p->name }}</a><div class="muted">{{ $p->sku }}{{ $p->category ? ' · '.$p->category->name : '' }}</div></td>
                    <td class="num"><b data-qty>{{ \App\Support\Qty::pretty($p->stock_qty) }}</b> {{ $p->unit }}</td>
                    <td class="num">{{ \App\Support\Qty::pretty($p->other_qty ?? 0) }}</td>
                    <td class="num">{{ \App\Support\Qty::pretty(\App\Support\Qty::fromMilli($p->totalMilli())) }}</td>
                    <td><span class="badge" data-label>{{ ['ok' => 'Aman', 'low' => 'Menipis', 'out' => 'Habis'][$p->stockState()] }}</span></td>
                    <td class="num" data-value>{{ \App\Support\Rupiah::format($p->inventoryValue()) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Tidak ada produk yang cocok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Mutasi terakhir</h2>
        <table>
            @foreach ($recent as $m)
                <tr><td>{{ $m->product?->name }}<div class="muted">{{ $m->type }} · {{ $m->source }} · {{ $m->created_at?->format('d/m H:i') }}</div></td>
                    <td class="num">{{ $m->qty_change > 0 ? '+' : '' }}{{ \App\Support\Qty::pretty($m->qty_change) }}</td></tr>
            @endforeach
        </table>
    </section>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/admin-stock.js') }}?v={{ @filemtime(public_path('js/admin-stock.js')) }}" defer data-feed="{{ route('admin.stock.feed') }}" data-cursor="{{ $cursor }}"></script>
@endpush
