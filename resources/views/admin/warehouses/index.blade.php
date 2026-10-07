@extends('layouts.admin')
@section('title', 'Gudang')
@section('heading', 'Gudang')
@section('content')
<div class="help"><strong>Gudang jual</strong> adalah stok yang tampil di web dan dijual kasir. Gudang lain (gudang belakang, cabang) menyimpan stok yang belum dijual; pindahkan lewat <a href="{{ route('admin.transfers.create') }}">Alih gudang</a> dan catat penerimaannya.</div>
<section class="card">
    <h2>Tambah gudang</h2>
    <form method="post" action="{{ route('admin.warehouses.store') }}" class="inline-form">@csrf
        <input name="code" placeholder="Kode (GD1)" maxlength="20" required aria-label="Kode">
        <input name="name" placeholder="Nama gudang" maxlength="100" required aria-label="Nama">
        <input name="address" placeholder="Alamat (opsional)" maxlength="200" aria-label="Alamat">
        <button class="btn">Tambah</button>
    </form>
</section>
<section class="card scroll">
    <table>
        <thead><tr><th>Gudang</th><th class="num">Jenis barang berstok</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($warehouses as $w)
            <tr>
                <td colspan="1">
                    <form method="post" action="{{ route('admin.warehouses.update', $w) }}" class="inline-form" id="w{{ $w->id }}">@csrf @method('PUT')
                        <input name="code" value="{{ $w->code }}" maxlength="20" aria-label="Kode" style="width:6rem">
                        <input name="name" value="{{ $w->name }}" maxlength="100" aria-label="Nama">
                        <input name="address" value="{{ $w->address }}" maxlength="200" placeholder="Alamat" aria-label="Alamat">
                        @unless ($w->is_main)<label class="check"><input type="checkbox" name="is_active" value="1" @checked($w->is_active)> Aktif</label>@endunless
                        <button class="btn btn--ghost btn--sm">Simpan</button>
                    </form>
                </td>
                <td class="num">{{ $w->is_main ? $mainItems : ($stock[$w->id]->items ?? 0) }}</td>
                <td>@if ($w->is_main)<span class="badge badge--ok">Gudang jual</span>@elseif ($w->is_active)<span class="badge">Aktif</span>@else<span class="badge badge--bad">Nonaktif</span>@endif</td>
                <td><a href="{{ route('admin.stock.mutation', ['warehouse' => $w->id]) }}">Mutasi</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endsection
