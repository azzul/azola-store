@extends('layouts.admin')
@section('title', $return->number)
@section('heading', 'Retur penjualan '.$return->number)
@section('actions')<button class="btn btn--ghost" type="button" onclick="window.print()">Cetak</button>@endsection
@section('content')
<div class="grid grid--2">
<section class="card"><dl class="kv"><dt>Tanggal</dt><dd>{{ $return->date->format('d/m/Y') }}</dd><dt>Faktur</dt><dd><a href="{{ route('admin.orders.show', $return->order_id) }}">{{ $return->order?->number }}</a></dd><dt>Alasan</dt><dd>{{ $return->reason ?: '-' }}</dd></dl></section>
<section class="card"><dl class="kv"><dt>Subtotal</dt><dd>@rp($return->subtotal)</dd><dt>Pajak</dt><dd>@rp($return->tax)</dd><dt><b>Total retur</b></dt><dd><b>@rp($return->total)</b></dd><dt>Potong piutang</dt><dd>@rp($return->offset_total)</dd><dt>Uang dikembalikan</dt><dd>@rp($return->paid_out){{ $return->refund_method ? ' ('.$return->refund_method.')' : '' }}</dd></dl></section>
</div>
<section class="card scroll"><h2>Barang</h2><table><thead><tr><th>Barang</th><th class="num">Jumlah</th><th class="num">Nilai</th><th>Stok</th></tr></thead><tbody>
@foreach ($return->items as $i)<tr><td>{{ $i->orderItem?->name ?? $i->product?->name }}</td><td class="num">{{ \App\Support\Qty::pretty($i->qty) }}</td><td class="num">@rp($i->line_total)</td><td>{{ $i->restock ? 'Masuk stok' : 'Tidak masuk stok' }}</td></tr>@endforeach
</tbody></table></section>
<section class="card scroll"><h2>Jurnal</h2><table><thead><tr><th>Jurnal</th><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead><tbody>
@foreach ($return->journals as $j)@foreach ($j->lines as $line)<tr><td>@if ($loop->first)<a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a>@endif</td><td>{{ $line->account->code }} {{ $line->account->name }}</td><td class="num">{{ $line->debit ? \App\Support\Rupiah::format($line->debit) : '' }}</td><td class="num">{{ $line->credit ? \App\Support\Rupiah::format($line->credit) : '' }}</td></tr>@endforeach @endforeach
</tbody></table></section>
@endsection
