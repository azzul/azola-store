@extends('layouts.admin')
@section('title', 'Bayar hutang')
@section('heading', 'Bayar hutang')
@section('content')
<form class="filters" method="get">
    <div><label for="supplier_pick">Supplier</label><select id="supplier_pick" name="supplier" onchange="this.form.submit()"><option value="">Pilih supplier</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($supplier?->id === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <noscript><button class="btn btn--ghost">Pilih</button></noscript>
</form>
@if ($supplier)
<form method="post" action="{{ route('admin.supplier-payments.store') }}">@csrf
<input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
<div class="kpis"><div class="kpi {{ $supplier->payable() > 0 ? 'kpi--warn' : '' }}"><b>@rp($supplier->payable())</b><span>Hutang ke {{ $supplier->name }}</span></div></div>
<section class="card scroll"><h2>Faktur belum lunas</h2>
    <table><thead><tr><th>Lunasi</th><th>Faktur</th><th>Jatuh tempo</th><th class="num">Sisa</th></tr></thead><tbody>
    @forelse ($open as $p)
        <tr><td><input type="checkbox" name="purchases[]" value="{{ $p->id }}" @checked($purchaseId === $p->id) data-due="{{ $p->outstanding() }}" aria-label="Pilih {{ $p->number }}" style="width:auto"></td><td><a href="{{ route('admin.purchases.show', $p) }}">{{ $p->number }}</a></td><td>{{ $p->due_date?->format('d/m/Y') ?: '-' }}@if ($p->isOverdue()) <span class="badge badge--bad">lewat</span>@endif</td><td class="num">@rp($p->outstanding())</td></tr>
    @empty<tr><td colspan="4" class="muted">Tidak ada hutang untuk supplier ini.</td></tr>@endforelse
    </tbody></table>
    <p class="hint">Centang faktur tertentu untuk dilunasi lebih dulu. Tanpa centang, pembayaran dialokasikan otomatis dari faktur yang paling lama jatuh tempo.</p>
</section>
@if ($open->isNotEmpty())
<section class="card"><div class="form-grid form-grid--tight">
    <div class="field"><label for="amount">Jumlah dibayar (Rp)</label><input id="amount" type="number" min="1" name="amount" value="{{ old('amount', $purchaseId ? $open->firstWhere('id', $purchaseId)?->outstanding() : '') }}" required></div>
    <div class="field"><label for="method">Dibayar lewat</label><select id="method" name="method"><option value="cash">Kas</option><option value="bank" @selected(old('method') === 'bank')>Bank</option></select></div>
    <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
    <div class="field"><label for="note">Catatan</label><input id="note" name="note" value="{{ old('note') }}" maxlength="200"></div>
</div></section>
<div class="actions"><button class="btn">Simpan pembayaran</button><a class="btn btn--ghost" href="{{ route('admin.payables.index') }}">Batal</a></div>
@endif
</form>
@else
<p class="muted">Pilih supplier untuk melihat hutangnya.</p>
@endif
@endsection
@push('scripts')
<script>
(function () {
    var amount = document.getElementById('amount'); if (!amount) return;
    document.querySelectorAll('input[name="purchases[]"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var sum = 0; document.querySelectorAll('input[name="purchases[]"]:checked').forEach(function (c) { sum += +c.dataset.due; });
            if (sum) amount.value = sum;
        });
    });
})();
</script>
@endpush
