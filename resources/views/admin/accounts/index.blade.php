@extends('layouts.admin')
@section('title', 'Akun')
@section('heading', 'Akun (bagan akun)')
@section('content')
<div class="help">Bagan akun dipakai semua jurnal otomatis. Akun bertanda <span class="badge">sistem</span> dipakai program (kas, bank, persediaan, penjualan, ...): kode dan namanya boleh diubah, tapi tidak bisa dihapus. Anda bebas menambah akun beban/pendapatan sendiri untuk Biaya dan Jurnal penyesuaian.</div>
<section class="card">
    <h2>{{ $edit ? 'Ubah akun '.$edit->code : 'Tambah akun' }}</h2>
    <form method="post" action="{{ $edit ? route('admin.accounts.update', $edit) : route('admin.accounts.store') }}">@csrf @if ($edit) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="code">Kode</label><input id="code" name="code" value="{{ old('code', $edit?->code) }}" maxlength="20" required></div>
            <div class="field"><label for="name">Nama akun</label><input id="name" name="name" value="{{ old('name', $edit?->name) }}" maxlength="120" required></div>
            <div class="field"><label for="type">Jenis</label>
                <select id="type" name="type" @disabled($edit && ($edit->key || $edit->lines()->exists()))>
                    @foreach ($types as $k => [$label, $normal])<option value="{{ $k }}" @selected(old('type', $edit?->type) === $k)>{{ $label }} (saldo normal {{ $normal === 'debit' ? 'debit' : 'kredit' }})</option>@endforeach
                </select>
                @if ($edit && ($edit->key || $edit->lines()->exists()))<div class="hint">Jenis dikunci karena akun sudah dipakai.</div>@endif</div>
            <div class="field"><label for="note">Catatan</label><input id="note" name="note" value="{{ old('note', $edit?->note) }}" maxlength="200"></div>
        </div>
        @if ($edit)<label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $edit->is_active))> Akun aktif (bisa dipilih di jurnal manual)</label>@endif
        <div class="actions"><button class="btn">{{ $edit ? 'Simpan perubahan' : 'Tambah akun' }}</button>@if ($edit)<a class="btn btn--ghost" href="{{ route('admin.accounts.index') }}">Batal</a>@endif</div>
    </form>
</section>
@foreach ($types as $type => [$label, $normal])
    @php($rows = $accounts->where('type', $type))
    @continue($rows->isEmpty())
    <section class="card scroll">
        <h2>{{ $label }}</h2>
        <table>
            <thead><tr><th>Kode</th><th>Nama</th><th class="num">Saldo</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($rows as $a)
                @php($b = $balances[$a->id] ?? null)
                @php($bal = $a->normal_balance === 'debit' ? (int) ($b->d ?? 0) - (int) ($b->c ?? 0) : (int) ($b->c ?? 0) - (int) ($b->d ?? 0))
                <tr>
                    <td>{{ $a->code }}</td>
                    <td><a href="{{ route('admin.reports.ledger', ['account' => $a->id]) }}">{{ $a->name }}</a>@if ($a->key) <span class="badge">sistem</span>@endif @unless ($a->is_active)<span class="badge badge--bad">nonaktif</span>@endunless</td>
                    <td class="num {{ $bal < 0 ? 'neg' : '' }}">@rp($bal)</td>
                    <td class="muted">{{ $b->n ?? 0 }} baris jurnal</td>
                    <td class="no-print">
                        <a class="btn btn--ghost btn--sm" href="{{ route('admin.accounts.index', ['edit' => $a->id]) }}">Ubah</a>
                        @unless ($a->key || $b)
                            <form method="post" action="{{ route('admin.accounts.destroy', $a) }}" class="inline-form" onsubmit="return confirm('Hapus akun {{ $a->name }}?')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Hapus</button></form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endforeach
@endsection
