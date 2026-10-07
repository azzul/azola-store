@extends('layouts.admin')
@section('title', 'Satuan')
@section('heading', 'Satuan')
@section('content')
<div class="help">Satuan dasar dipakai produk (pcs, kg, liter). Satuan besar seperti dus atau lusin diatur di <a href="{{ route('admin.conversions.index') }}">Konversi satuan</a>. Mengganti nama satuan otomatis mengubah semua produk yang memakainya.</div>
<section class="card">
    <h2>Tambah satuan</h2>
    <form method="post" action="{{ route('admin.units.store') }}" class="inline-form">@csrf
        <input type="text" name="name" placeholder="mis. dus" maxlength="20" required aria-label="Nama satuan">
        <input type="text" name="note" placeholder="Keterangan (opsional)" maxlength="120" aria-label="Keterangan">
        <button class="btn">Tambah</button>
    </form>
</section>
<section class="card scroll">
    <table>
        <thead><tr><th>Satuan</th><th>Keterangan</th><th class="num">Dipakai produk</th><th class="num">Dipakai konversi</th><th></th></tr></thead>
        <tbody>
        @forelse ($units as $u)
            <tr>
                <td colspan="2">
                    <form method="post" action="{{ route('admin.units.update', $u) }}" class="inline-form">@csrf @method('PUT')
                        <input type="text" name="name" value="{{ $u->name }}" maxlength="20" required aria-label="Nama satuan {{ $u->name }}">
                        <input type="text" name="note" value="{{ $u->note }}" maxlength="120" placeholder="Keterangan" aria-label="Keterangan">
                        <button class="btn btn--ghost btn--sm">Simpan</button>
                    </form>
                </td>
                <td class="num">{{ $usage[$u->name] ?? 0 }}</td>
                <td class="num">{{ $conv[$u->name] ?? 0 }}</td>
                <td>
                    <form method="post" action="{{ route('admin.units.destroy', $u) }}" onsubmit="return confirm('Hapus satuan {{ $u->name }}?')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Hapus</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada satuan.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
