@extends('layouts.admin')
@section('title', $purchase->number)
@section('heading', 'Pembelian '.$purchase->number)
@section('actions')
    @unless ($purchase->isCancelled())
        <a class="btn" href="{{ route('admin.purchases.edit', $purchase) }}">Ubah</a>
        <a class="btn btn--ghost" href="{{ route('admin.purchase-returns.create', ['purchase' => $purchase->id]) }}">Retur barang</a>
        @if ($purchase->outstanding() > 0)<a class="btn btn--ghost" href="{{ route('admin.supplier-payments.create', ['supplier' => $purchase->supplier_id, 'purchase' => $purchase->id]) }}">Bayar hutang</a>@endif
    @endunless
    <button class="btn btn--ghost" type="button" onclick="window.print()">Cetak</button>
@endsection
@section('content')
<div class="grid grid--2">
    <section class="card">
        <dl class="kv">
            <dt>Status</dt><dd>@if ($purchase->isCancelled())<span class="badge badge--bad">Dibatalkan {{ $purchase->cancelled_at?->format('d/m/Y H:i') }}</span>@elseif ($purchase->outstanding() > 0)<span class="badge badge--warn">Belum lunas</span>@else<span class="badge badge--ok">Lunas</span>@endif</dd>
            <dt>Tanggal</dt><dd>{{ $purchase->date->format('d/m/Y') }}</dd>
            <dt>Supplier</dt><dd>@if ($purchase->supplier)<a href="{{ route('admin.suppliers.show', $purchase->supplier) }}">{{ $purchase->supplier->name }}</a>@else - @endif</dd>
            <dt>Faktur supplier</dt><dd>{{ $purchase->supplier_invoice ?: '-' }}</dd>
            <dt>Gudang</dt><dd>{{ $purchase->warehouse?->name ?? '-' }}</dd>
            <dt>Cara bayar</dt><dd>{{ ['cash' => 'Tunai (kas)', 'bank' => 'Transfer (bank)', 'credit' => 'Kredit'][$purchase->payment_method] }}{{ $purchase->due_date ? ', tempo '.$purchase->due_date->format('d/m/Y') : '' }}</dd>
            <dt>Dicatat oleh</dt><dd>{{ $purchase->user?->name ?? '-' }}</dd>
            <dt>Catatan</dt><dd>{{ $purchase->note ?: '-' }}</dd>
        </dl>
    </section>
    <section class="card">
        <div class="totals" style="margin:0">
            <div><span>Subtotal</span><b>@rp($purchase->subtotal)</b></div>
            <div><span>Potongan</span><b>-@rp($purchase->discount)</b></div>
            <div class="grand"><span>Total</span><b>@rp($purchase->grand_total)</b></div>
            <div><span>Dibayar / dipotong</span><b>@rp($purchase->paid_total)</b></div>
            <div><span>Sisa hutang</span><b class="{{ $purchase->outstanding() > 0 ? 'neg' : '' }}">@rp($purchase->outstanding())</b></div>
            @if ($purchase->returned_total)<div><span>Nilai retur</span><b>@rp($purchase->returned_total)</b></div>@endif
        </div>
    </section>
</div>
<section class="card scroll"><h2>Barang</h2>
    <table><thead><tr><th>Barang</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th><th class="num">Masuk stok</th></tr></thead><tbody>
    @foreach ($purchase->items as $i)
        <tr><td>{{ $i->product?->name ?? 'Produk dihapus' }}<div class="muted">{{ $i->product?->sku }}</div></td><td class="num">@qty($i->qty) {{ $i->unit }}</td><td class="num">@rp($i->price)</td><td class="num">@rp($i->line_total)</td><td class="num">@qty($i->base_qty) {{ $i->product?->unit }}</td></tr>
    @endforeach
    </tbody></table>
</section>
@if ($purchase->allocations->isNotEmpty() || $returns->isNotEmpty())
<section class="card scroll"><h2>Pelunasan dan retur</h2>
    <table><thead><tr><th>Dokumen</th><th>Tanggal</th><th class="num">Jumlah</th></tr></thead><tbody>
    @foreach ($purchase->allocations as $a)
        <tr><td>{{ $a->source?->number }} <span class="muted">({{ $a->source_type === \App\Models\SupplierPayment::class ? 'pembayaran' : 'potongan retur' }})</span></td><td>{{ $a->created_at->format('d/m/Y') }}</td><td class="num">@rp($a->amount)</td></tr>
    @endforeach
    @foreach ($returns as $r)<tr><td><a href="{{ route('admin.purchase-returns.show', $r) }}">{{ $r->number }}</a> <span class="muted">(retur{{ $r->status === 'cancelled' ? ', batal' : '' }})</span></td><td>{{ $r->date->format('d/m/Y') }}</td><td class="num">@rp($r->total)</td></tr>@endforeach
    </tbody></table>
</section>
@endif
<section class="card scroll"><h2>Jurnal</h2>
    <table><thead><tr><th>Jurnal</th><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead><tbody>
    @forelse ($purchase->journals as $j)
        @foreach ($j->lines as $line)
            <tr><td>@if ($loop->first)<a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a> <span class="muted">{{ $j->date->format('d/m/Y') }}{{ $j->reversal_of_id ? ', pembalik' : '' }}</span>@endif</td><td>{{ $line->account->code }} {{ $line->account->name }}</td><td class="num">{{ $line->debit ? \App\Support\Rupiah::format($line->debit) : '' }}</td><td class="num">{{ $line->credit ? \App\Support\Rupiah::format($line->credit) : '' }}</td></tr>
        @endforeach
    @empty<tr><td colspan="4" class="muted">Tidak ada jurnal.</td></tr>@endforelse
    </tbody></table>
</section>
@unless ($purchase->isCancelled())
<section class="card no-print"><h2>Batalkan faktur</h2>
    <form method="post" action="{{ route('admin.purchases.cancel', $purchase) }}" class="inline-form" onsubmit="return confirm('Batalkan faktur {{ $purchase->number }}? Stok dikurangi dan jurnal dibalik.')">@csrf
        <input name="reason" placeholder="Alasan (opsional)" maxlength="200" aria-label="Alasan"><button class="btn btn--danger btn--sm">Batalkan</button>
    </form>
    <p class="hint">Tidak bisa dibatalkan bila sudah ada pembayaran hutang atau retur aktif; batalkan itu dulu.</p>
</section>
@endunless
@endsection
