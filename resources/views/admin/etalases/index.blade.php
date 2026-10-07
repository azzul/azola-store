@extends('layouts.admin')
@section('title', 'Etalase')
@section('heading', 'Etalase')
@section('content')
<p class="muted">Etalase adalah rak tampilan di toko, misalnya "Terlaris" atau "Promo minggu ini". Satu produk boleh ada di beberapa etalase. Pilih produknya di halaman Katalog toko.</p>
<div class="grid grid--2" style="align-items:start">
    <section class="card">
        <table>
            <thead><tr><th>Etalase</th><th class="num">Produk</th><th></th></tr></thead>
            <tbody>
            @forelse ($etalases as $e)
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.etalases.update', $e) }}" class="stack">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $e->name }}" required maxlength="80" aria-label="Nama etalase">
                            <input type="text" name="description" value="{{ $e->description }}" maxlength="300" placeholder="Deskripsi singkat" aria-label="Deskripsi etalase">
                            <div class="row"><label class="check"><input type="checkbox" name="is_visible" value="1" @checked($e->is_visible)> Tampil di toko</label>
                                <input type="number" name="sort_order" value="{{ $e->sort_order }}" min="0" style="width:5rem" aria-label="Urutan"><button class="btn btn--ghost btn--sm">Simpan</button></div>
                        </form>
                    </td>
                    <td class="num">{{ $e->groups_count }}</td>
                    <td class="num"><form method="post" action="{{ route('admin.etalases.destroy', $e) }}" onsubmit="return confirm('Hapus etalase ini? Produknya tetap ada.')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Hapus</button></form></td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Belum ada etalase.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <form class="card" method="post" action="{{ route('admin.etalases.store') }}">
        @csrf
        <h2>Tambah etalase</h2>
        <div class="field"><label for="name">Nama</label><input id="name" type="text" name="name" required maxlength="80"></div>
        <div class="field"><label for="description">Deskripsi singkat</label><input id="description" type="text" name="description" maxlength="300"></div>
        <div class="field"><label for="sort_order">Urutan</label><input id="sort_order" type="number" min="0" name="sort_order" value="0"></div>
        <button class="btn">Tambah etalase</button>
    </form>
</div>
@endsection
