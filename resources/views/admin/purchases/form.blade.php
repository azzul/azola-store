@extends('layouts.admin')
@php($isNew = ! $purchase->exists)
@section('title', $isNew ? 'Input pembelian' : 'Ubah '.$purchase->number)
@section('heading', $isNew ? 'Input pembelian' : 'Ubah pembelian '.$purchase->number)
@section('content')
@unless ($isNew)<div class="help">Mengubah faktur otomatis mengoreksi stok, HPP, dan jurnal (jurnal lama dibalik di tanggalnya, lalu dibuat jurnal baru).@if ($allocated > 0) Faktur ini sudah punya pelunasan/potongan retur {{ \App\Support\Rupiah::format($allocated) }}, jadi tetap berstatus kredit.@endif</div>@endunless
<form method="post" action="{{ $isNew ? route('admin.purchases.store') : route('admin.purchases.update', $purchase) }}" id="pform">@csrf @if (! $isNew) @method('PUT') @endif
<section class="card">
    <div class="form-grid form-grid--tight">
        <div class="field"><label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id"><option value="">Tanpa supplier (beli tunai di pasar)</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" data-term="{{ $s->term_days }}" @selected((int) old('supplier_id', $purchase->supplier_id) === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
        <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" value="{{ old('date', $purchase->date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
        <div class="field"><label for="supplier_invoice">No. faktur supplier</label><input id="supplier_invoice" name="supplier_invoice" value="{{ old('supplier_invoice', $purchase->supplier_invoice) }}" maxlength="80"></div>
        <div class="field"><label for="warehouse_id">Masuk ke gudang</label><select id="warehouse_id" name="warehouse_id">@foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected((int) old('warehouse_id', $purchase->warehouse_id) === $w->id)>{{ $w->name }}</option>@endforeach</select></div>
        <div class="field"><label for="payment_method">Cara bayar</label>
            <select id="payment_method" name="payment_method" @if ($allocated > 0) data-locked @endif>@foreach (['cash' => 'Tunai (kas)', 'bank' => 'Transfer (bank)', 'credit' => 'Kredit (hutang)'] as $k => $v)<option value="{{ $k }}" @selected(old('payment_method', $purchase->payment_method) === $k) @disabled($allocated > 0 && $k !== 'credit')>{{ $v }}</option>@endforeach</select></div>
        <div class="field credit-only"><label for="due_date">Jatuh tempo</label><input id="due_date" type="date" name="due_date" value="{{ old('due_date', $purchase->due_date?->format('Y-m-d')) }}"><div class="hint">Kosong = tanggal + tempo supplier.</div></div>
        <div class="field credit-only"><label for="down_payment">Uang muka (Rp)</label><input id="down_payment" type="number" min="0" name="down_payment" value="{{ old('down_payment', $downPayment) }}"></div>
        <div class="field credit-only"><label for="down_payment_via">Uang muka lewat</label><select id="down_payment_via" name="down_payment_via"><option value="cash" @selected(old('down_payment_via') !== 'bank')>Kas</option><option value="bank" @selected(old('down_payment_via') === 'bank')>Bank</option></select></div>
    </div>
</section>
<section class="card">
    <h2>Barang</h2>
    <x-admin.lines name="items" mode="price" price-from="cost" :initial="$initial" />
    <div class="totals">
        <div><span>Subtotal</span><b data-lines-subtotal="items">Rp0</b></div>
        <div><span><label for="discount" style="display:inline">Potongan faktur</label></span><input id="discount" type="number" min="0" name="discount" data-lines-discount="items" value="{{ old('discount', $purchase->discount ?? 0) }}" style="width:9rem"></div>
        <div class="grand"><span>Total faktur</span><b data-lines-grand="items">Rp0</b></div>
    </div>
</section>
<section class="card"><div class="field"><label for="note">Catatan</label><input id="note" name="note" value="{{ old('note', $purchase->note) }}" maxlength="500"></div></section>
<div class="actions"><button class="btn">{{ $isNew ? 'Simpan pembelian' : 'Simpan perubahan' }}</button><a class="btn btn--ghost" href="{{ $isNew ? route('admin.purchases.index') : route('admin.purchases.show', $purchase) }}">Batal</a></div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    var method = document.getElementById('payment_method'), supplier = document.getElementById('supplier_id'), due = document.getElementById('due_date'), date = document.getElementById('date');
    function toggle() { document.querySelectorAll('.credit-only').forEach(function (el) { el.hidden = method.value !== 'credit'; }); }
    function suggestDue() {
        var opt = supplier.selectedOptions[0]; if (!opt || due.value || !date.value) return;
        var d = new Date(date.value); d.setDate(d.getDate() + (+opt.dataset.term || 0)); due.placeholder = d.toISOString().slice(0, 10);
    }
    method.addEventListener('change', toggle); supplier.addEventListener('change', suggestDue); date.addEventListener('change', suggestDue);
    toggle(); suggestDue();
})();
</script>
@endpush
