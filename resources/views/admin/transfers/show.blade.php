@extends('layouts.admin')
@section('title', $transfer->number)
@section('heading', 'Alih gudang '.$transfer->number)
@section('actions')<button class="btn btn--ghost" type="button" onclick="window.print()">Cetak</button>@endsection
@section('content')
<section class="card"><dl class="kv">
    <dt>Status</dt><dd><x-admin.badge :value="$transfer->status" :map="['sent' => ['Dalam perjalanan', 'warn'], 'received' => ['Diterima', 'ok'], 'cancelled' => ['Batal', 'bad']]" /></dd>
    <dt>Tanggal kirim</dt><dd>{{ $transfer->date->format('d/m/Y') }}</dd>
    <dt>Dari</dt><dd>{{ $transfer->from->name }}</dd><dt>Ke</dt><dd>{{ $transfer->to->name }}</dd>
    @if ($transfer->received_on)<dt>Diterima</dt><dd>{{ $transfer->received_on->format('d/m/Y') }}</dd>@endif
    <dt>Catatan</dt><dd>{{ $transfer->note ?: '-' }}</dd>
</dl></section>
@if ($transfer->status === 'sent')
<form method="post" action="{{ route('admin.transfers.receive', $transfer) }}">@csrf
<section class="card scroll"><h2>Catat penerimaan</h2>
    <div class="field" style="max-width:14rem"><label for="date">Tanggal diterima</label><input id="date" type="date" name="date" value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" required></div>
    <table><thead><tr><th>Barang</th><th class="num">Dikirim</th><th>Diterima</th></tr></thead><tbody>
    @foreach ($transfer->items as $i)
        <tr><td>{{ $i->product->name }}<div class="muted">{{ $i->product->sku }}</div></td><td class="num">@qty($i->qty) {{ $i->product->unit }}</td>
        <td><input type="number" step="any" min="0" max="{{ (float) $i->qty }}" name="received[{{ $i->product_id }}]" value="{{ (float) $i->qty }}" aria-label="Diterima {{ $i->product->name }}" style="width:8rem"></td></tr>
    @endforeach
    </tbody></table>
    <p class="hint">Kurangi jumlah bila ada yang hilang/rusak di jalan; selisihnya dicatat sebagai kerusakan & penyusutan stok.</p>
    <div class="actions"><button class="btn">Terima barang</button></div>
</section></form>
<section class="card no-print"><form method="post" action="{{ route('admin.transfers.cancel', $transfer) }}" onsubmit="return confirm('Batalkan kiriman? Stok kembali ke gudang asal.')">@csrf<button class="btn btn--danger btn--sm">Batalkan kiriman</button></form></section>
@else
<section class="card scroll"><h2>Barang</h2><table><thead><tr><th>Barang</th><th class="num">Dikirim</th><th class="num">Diterima</th></tr></thead><tbody>
    @foreach ($transfer->items as $i)<tr><td>{{ $i->product->name }}</td><td class="num">@qty($i->qty) {{ $i->product->unit }}</td><td class="num">{{ $i->received_qty !== null ? \App\Support\Qty::pretty($i->received_qty).' '.$i->product->unit : '-' }}</td></tr>@endforeach
</tbody></table></section>
@if ($transfer->journals->isNotEmpty())<section class="card scroll"><h2>Jurnal selisih</h2><table><tbody>@foreach ($transfer->journals as $j)@foreach ($j->lines as $line)<tr><td>@if ($loop->first)<a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a>@endif</td><td>{{ $line->account->name }}</td><td class="num">{{ $line->debit ? \App\Support\Rupiah::format($line->debit) : '' }}</td><td class="num">{{ $line->credit ? \App\Support\Rupiah::format($line->credit) : '' }}</td></tr>@endforeach @endforeach</tbody></table></section>@endif
@endif
@endsection
