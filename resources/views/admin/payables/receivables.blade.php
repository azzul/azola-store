@extends('layouts.admin')
@section('title', 'Piutang supplier')
@section('heading', 'Piutang supplier')
@section('content')
<div class="help">Piutang supplier muncul bila retur pembelian diselesaikan sebagai <em>piutang supplier</em> (uang akan dikembalikan nanti) atau melebihi hutang. Catat uang yang diterima di sini.</div>
<div class="kpis"><div class="kpi"><b>@rp($total)</b><span>Total piutang supplier</span></div></div>
<div class="grid grid--2">
<section class="card scroll"><h2>Saldo per supplier</h2>
    <table><thead><tr><th>Supplier</th><th class="num">Saldo</th></tr></thead><tbody>
    @forelse ($rows as $r)<tr><td><a href="{{ route('admin.supplier-receivables.index', ['supplier' => $r['supplier']->id]) }}">{{ $r['supplier']->name }}</a></td><td class="num">@rp($r['balance'])</td></tr>
    @empty<tr><td colspan="2" class="muted">Tidak ada piutang supplier.</td></tr>@endforelse
    </tbody></table>
</section>
<section class="card"><h2>Terima pengembalian dana</h2>
    <form method="post" action="{{ route('admin.supplier-receivables.receive') }}">@csrf
        <div class="field"><label for="supplier_id">Supplier</label><select id="supplier_id" name="supplier_id" required><option value="">Pilih supplier</option>@foreach ($allSuppliers as $s)<option value="{{ $s->id }}" @selected($supplier?->id === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
        <div class="form-grid form-grid--tight">
            <div class="field"><label for="amount">Jumlah (Rp)</label><input id="amount" type="number" min="1" name="amount" value="{{ old('amount', $supplier?->receivable() ?: '') }}" required></div>
            <div class="field"><label for="method">Diterima lewat</label><select id="method" name="method"><option value="cash">Kas</option><option value="bank">Bank</option></select></div>
            <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" required></div>
            <div class="field"><label for="note">Catatan</label><input id="note" name="note" maxlength="200"></div>
        </div>
        <button class="btn">Catat penerimaan</button>
    </form>
</section>
</div>
@if ($card)
<section class="card"><h2>Kartu piutang {{ $supplier->name }}</h2>
    <x-admin.period :from="$from" :to="$to"><input type="hidden" name="supplier" value="{{ $supplier->id }}"></x-admin.period>
    @include('admin.partials.subledger', ['card' => $card, 'plus' => 'Piutang naik', 'minus' => 'Diterima', 'side' => 'debit'])
</section>
@endif
<section class="card scroll"><h2>Penerimaan terakhir</h2><table><tbody>
    @forelse ($receipts as $p)<tr><td>{{ $p->number }}</td><td>{{ $p->date->format('d/m/Y') }}</td><td>{{ $p->supplier->name }}</td><td>{{ $p->method === 'cash' ? 'Kas' : 'Bank' }}</td><td class="num">@rp($p->amount)</td></tr>@empty<tr><td class="muted">Belum ada.</td></tr>@endforelse
</tbody></table></section>
@endsection
