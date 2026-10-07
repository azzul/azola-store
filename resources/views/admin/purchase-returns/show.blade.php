@extends('layouts.admin')
@section('title', $return->number)
@section('heading', 'Retur pembelian '.$return->number)
@section('actions')<button class="btn btn--ghost" type="button" onclick="window.print()">Cetak</button>@endsection
@section('content')
<div class="grid grid--2">
    <section class="card"><dl class="kv">
        <dt>Status</dt><dd>@if ($return->status === 'cancelled')<span class="badge badge--bad">Dibatalkan</span>@else<span class="badge badge--ok">Sah</span>@endif</dd>
        <dt>Tanggal</dt><dd>{{ $return->date->format('d/m/Y') }}</dd>
        <dt>Supplier</dt><dd>@if ($return->supplier)<a href="{{ route('admin.suppliers.show', $return->supplier) }}">{{ $return->supplier->name }}</a>@endif</dd>
        <dt>Faktur asal</dt><dd>@if ($return->purchase)<a href="{{ route('admin.purchases.show', $return->purchase) }}">{{ $return->purchase->number }}</a>@else - @endif</dd>
        <dt>Gudang</dt><dd>{{ $return->warehouse?->name }}</dd>
        <dt>Penyelesaian</dt><dd>{{ ['payable' => 'Potong hutang', 'cash' => 'Tunai', 'bank' => 'Transfer', 'receivable' => 'Piutang supplier'][$return->settlement] }}</dd>
        <dt>Alasan</dt><dd>{{ $return->reason ?: '-' }}</dd>
    </dl></section>
    <section class="card"><div class="totals" style="margin:0"><div class="grand"><span>Total retur</span><b>@rp($return->total)</b></div></div></section>
</div>
<section class="card scroll"><h2>Barang</h2><table><thead><tr><th>Barang</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead><tbody>
    @foreach ($return->items as $i)<tr><td>{{ $i->product?->name }}<div class="muted">{{ $i->product?->sku }}</div></td><td class="num">@qty($i->qty) {{ $i->unit }}</td><td class="num">@rp($i->price)</td><td class="num">@rp($i->line_total)</td></tr>@endforeach
</tbody></table></section>
<section class="card scroll"><h2>Jurnal</h2><table><thead><tr><th>Jurnal</th><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead><tbody>
    @foreach ($return->journals as $j)@foreach ($j->lines as $line)<tr><td>@if ($loop->first)<a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a>@endif</td><td>{{ $line->account->code }} {{ $line->account->name }}</td><td class="num">{{ $line->debit ? \App\Support\Rupiah::format($line->debit) : '' }}</td><td class="num">{{ $line->credit ? \App\Support\Rupiah::format($line->credit) : '' }}</td></tr>@endforeach @endforeach
</tbody></table></section>
@if ($return->status !== 'cancelled')
<section class="card no-print"><form method="post" action="{{ route('admin.purchase-returns.cancel', $return) }}" onsubmit="return confirm('Batalkan retur ini? Stok kembali dan jurnal dibalik.')">@csrf<button class="btn btn--danger btn--sm">Batalkan retur</button></form></section>
@endif
@endsection
