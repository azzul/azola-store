@extends('layouts.admin')
@section('title', $adjustment->number)
@section('heading', 'Penyesuaian '.$adjustment->number)
@section('content')
<section class="card"><dl class="kv"><dt>Tanggal</dt><dd>{{ $adjustment->date->format('d/m/Y') }}</dd><dt>Alasan</dt><dd>{{ $reasons[$adjustment->reason] ?? $adjustment->reason }}</dd><dt>Gudang</dt><dd>{{ $adjustment->warehouse?->name }}</dd><dt>Catatan</dt><dd>{{ $adjustment->note ?: '-' }}</dd><dt>Nilai bersih</dt><dd class="{{ $adjustment->value_total < 0 ? 'neg' : '' }}">@rp($adjustment->value_total)</dd></dl></section>
<section class="card scroll"><h2>Barang</h2><table><thead><tr><th>Barang</th><th class="num">Selisih</th><th class="num">HPP</th><th class="num">Nilai</th></tr></thead><tbody>
@foreach ($adjustment->items as $i)<tr><td>{{ $i->product?->name }}<div class="muted">{{ $i->product?->sku }}</div></td><td class="num">{{ ((float) $i->qty_change > 0 ? '+' : '').\App\Support\Qty::pretty($i->qty_change) }} {{ $i->product?->unit }}</td><td class="num">@rp($i->unit_cost)</td><td class="num">@rp(\App\Support\Qty::value(\App\Support\Qty::toMilli($i->qty_change), (int) $i->unit_cost))</td></tr>@endforeach
</tbody></table></section>
<section class="card scroll"><h2>Jurnal</h2><table><thead><tr><th>Jurnal</th><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead><tbody>
@foreach ($adjustment->journals as $j)@foreach ($j->lines as $line)<tr><td>@if ($loop->first)<a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a>@endif</td><td>{{ $line->account->code }} {{ $line->account->name }}</td><td class="num">{{ $line->debit ? \App\Support\Rupiah::format($line->debit) : '' }}</td><td class="num">{{ $line->credit ? \App\Support\Rupiah::format($line->credit) : '' }}</td></tr>@endforeach @endforeach
</tbody></table></section>
@endsection
