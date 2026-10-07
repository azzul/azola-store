@extends('layouts.admin')
@section('title', 'Depresiasi')
@section('heading', 'Aset tetap & depresiasi')
@section('content')
<div class="help">Penyusutan garis lurus per bulan: (harga perolehan − nilai sisa) ÷ umur. Jalankan depresiasi tiap akhir bulan; bulan yang terlewat dikejar otomatis dan tidak pernah dihitung dua kali.</div>
<div class="kpis"><div class="kpi"><b>@rp($cost)</b><span>Harga perolehan aset aktif</span></div><div class="kpi"><b>@rp($accumulated)</b><span>Akumulasi penyusutan</span></div><div class="kpi"><b>@rp($cost - $accumulated)</b><span>Nilai buku</span></div></div>
<div class="grid grid--2">
<form class="card" method="post" action="{{ route('admin.assets.depreciate') }}">@csrf<h2>Jalankan depresiasi</h2>
    <div class="field"><label for="period">Sampai bulan</label><input id="period" type="month" name="period" value="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required></div>
    <button class="btn">Hitung & jurnalkan</button></form>
<form class="card" method="post" action="{{ route('admin.assets.store') }}">@csrf<h2>Tambah aset</h2>
    <div class="form-grid form-grid--tight">
        <div class="field"><label for="name">Nama aset</label><input id="name" name="name" required maxlength="120" value="{{ old('name') }}"></div>
        <div class="field"><label for="acquired_on">Tanggal perolehan</label><input id="acquired_on" type="date" name="acquired_on" required value="{{ old('acquired_on', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}"></div>
        <div class="field"><label for="cost">Harga perolehan (Rp)</label><input id="cost" type="number" min="1" name="cost" required value="{{ old('cost') }}"></div>
        <div class="field"><label for="salvage">Nilai sisa (Rp)</label><input id="salvage" type="number" min="0" name="salvage" value="{{ old('salvage', 0) }}"></div>
        <div class="field"><label for="life_months">Umur (bulan)</label><input id="life_months" type="number" min="1" name="life_months" required value="{{ old('life_months', 48) }}"></div>
        <div class="field"><label for="paid_via">Dibayar dari</label><select id="paid_via" name="paid_via"><option value="cash">Kas</option><option value="bank">Bank</option><option value="payable">Hutang usaha</option><option value="equity">Modal pemilik</option></select></div>
    </div>
    <button class="btn">Simpan aset</button>
</form>
</div>
<section class="card scroll"><h2>Daftar aset</h2><table><thead><tr><th>Kode</th><th>Aset</th><th>Diperoleh</th><th class="num">Perolehan</th><th class="num">Per bulan</th><th class="num">Akumulasi</th><th class="num">Nilai buku</th><th>Status</th><th></th></tr></thead><tbody>
@forelse ($assets as $a)
<tr><td>{{ $a->code }}</td><td>{{ $a->name }}<div class="muted">{{ $a->life_months }} bulan · sisa @rp($a->salvage)</div></td><td>{{ $a->acquired_on->format('d/m/Y') }}</td><td class="num">@rp($a->cost)</td><td class="num">@rp($a->monthly())</td><td class="num">@rp($a->depreciated)</td><td class="num">@rp($a->bookValue())</td>
<td>@if ($a->status === 'active')<span class="badge badge--ok">aktif</span>@else<span class="badge">dilepas</span>@endif</td>
<td class="no-print">@if ($a->status === 'active')<form method="post" action="{{ route('admin.assets.dispose', $a) }}" style="display:flex;gap:.3rem" onsubmit="return confirm('Lepas aset ini?')">@csrf<input type="number" min="0" name="proceeds" value="0" aria-label="Hasil penjualan {{ $a->name }}" style="width:7rem"><select name="via" aria-label="Via"><option value="cash">Kas</option><option value="bank">Bank</option></select><button class="btn btn--ghost btn--sm">Lepas</button></form>@endif</td></tr>
@empty<tr><td colspan="9" class="muted">Belum ada aset tetap.</td></tr>@endforelse
</tbody></table></section>
<section class="card scroll"><h2>Penyusutan terakhir</h2><table><tbody>@forelse ($recent as $r)<tr><td>{{ $r->period }}</td><td>{{ $r->asset?->code }} {{ $r->asset?->name }}</td><td class="num">@rp($r->amount)</td></tr>@empty<tr><td class="muted">Belum ada.</td></tr>@endforelse</tbody></table></section>
@endsection
