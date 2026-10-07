@extends('layouts.admin')
@section('title', 'Biaya & kas')
@section('heading', 'Biaya & kas masuk/keluar')
@section('content')
<div class="help">Catat biaya operasional (listrik, sewa, gaji, ongkir) atau kas masuk di luar penjualan. Jurnal terbentuk otomatis. Salah catat? Batalkan, sistem membuat jurnal pembalik.</div>
<div class="grid grid--2">
<form class="card" method="post" action="{{ route('admin.expenses.store') }}">@csrf<h2>Catat transaksi</h2>
    <div class="form-grid form-grid--tight">
        <div class="field"><label for="kind">Jenis</label><select id="kind" name="kind"><option value="expense">Biaya (kas keluar)</option><option value="income" @selected(old('kind') === 'income')>Kas masuk lain</option></select></div>
        <div class="field"><label for="account_id">Akun</label><select id="account_id" name="account_id" required><optgroup label="Beban">@foreach ($expenseAccounts as $a)<option value="{{ $a->id }}" data-kind="expense">{{ $a->code }} {{ $a->name }}</option>@endforeach</optgroup><optgroup label="Pendapatan / lainnya">@foreach ($incomeAccounts as $a)<option value="{{ $a->id }}" data-kind="income">{{ $a->code }} {{ $a->name }}</option>@endforeach</optgroup></select></div>
        <div class="field"><label for="amount">Jumlah (Rp)</label><input id="amount" type="number" min="1" name="amount" required value="{{ old('amount') }}"></div>
        <div class="field"><label for="via">Lewat</label><select id="via" name="via"><option value="cash">Kas</option><option value="bank">Bank</option></select></div>
        <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" required value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}"></div>
        <div class="field"><label for="description">Keterangan</label><input id="description" name="description" maxlength="200" value="{{ old('description') }}"></div>
    </div>
    <button class="btn">Simpan</button>
</form>
<section class="card"><div class="kpis" style="grid-template-columns:1fr 1fr"><div class="kpi kpi--bad"><b>@rp($expense)</b><span>Biaya periode ini</span></div><div class="kpi"><b>@rp($income)</b><span>Kas masuk lain</span></div></div></section>
</div>
<x-admin.period :from="$from" :to="$to" :csv="true"><div><label for="kf">Jenis</label><select id="kf" name="kind"><option value="">Semua</option><option value="expense" @selected(request('kind') === 'expense')>Biaya</option><option value="income" @selected(request('kind') === 'income')>Kas masuk</option></select></div></x-admin.period>
<section class="card scroll"><table><thead><tr><th>Tanggal</th><th>Jurnal</th><th>Keterangan</th><th class="num">Jumlah</th><th></th></tr></thead><tbody>
@forelse ($journals as $j)<tr @class(['muted' => $j->isReversed()])><td>{{ $j->date->format('d/m/Y') }}</td><td><a href="{{ route('admin.journals.show', $j) }}">{{ $j->number }}</a></td><td>{{ $j->description }}@if ($j->isReversed()) <span class="badge badge--bad">batal</span>@endif</td><td class="num">@rp($j->total)</td>
<td class="no-print">@unless ($j->isReversed())<form method="post" action="{{ route('admin.expenses.cancel', $j) }}" onsubmit="return confirm('Batalkan transaksi ini?')">@csrf<button class="btn btn--ghost btn--sm">Batalkan</button></form>@endunless</td></tr>
@empty<tr><td colspan="5" class="muted">Belum ada transaksi pada periode ini.</td></tr>@endforelse
</tbody></table></section>
@endsection
