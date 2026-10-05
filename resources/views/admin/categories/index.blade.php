@extends('layouts.admin')
@section('title', 'Kategori')
@section('heading', 'Kategori')
@section('content')
<div class="grid grid--2" style="align-items:start">
    <section class="card">
        <table>
            <thead><tr><th>Nama</th><th class="num">Produk</th><th></th></tr></thead>
            <tbody>
            @forelse ($categories as $c)
                <tr><td>{{ $c->name }}<div class="muted">{{ $c->description }}</div></td><td class="num">{{ $c->products_count }}</td>
                    <td class="num"><form method="post" action="{{ route('admin.categories.destroy', $c) }}" onsubmit="return confirm('Hapus kategori ini? Produknya tetap ada.')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Hapus</button></form></td></tr>
            @empty
                <tr><td colspan="3" class="muted">Belum ada kategori.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <form class="card" method="post" action="{{ route('admin.categories.store') }}">
        @csrf
        <h2>Tambah kategori</h2>
        <div class="field"><label for="name">Nama</label><input id="name" type="text" name="name" required maxlength="80"></div>
        <div class="field"><label for="description">Deskripsi singkat</label><input id="description" type="text" name="description" maxlength="300"></div>
        <div class="field"><label for="sort_order">Urutan</label><input id="sort_order" type="number" min="0" name="sort_order" value="0"></div>
        <button class="btn">Tambah kategori</button>
    </form>
</div>
@endsection
