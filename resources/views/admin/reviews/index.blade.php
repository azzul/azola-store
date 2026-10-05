@extends('layouts.admin')
@section('title', 'Ulasan')
@section('heading', 'Ulasan pelanggan')
@section('content')
<div class="row" style="margin-bottom:1rem">
    <a class="btn {{ $tab === 'menunggu' ? '' : 'btn--ghost' }}" href="{{ route('admin.reviews.index') }}">Menunggu ({{ $pending }})</a>
    <a class="btn {{ $tab === 'tampil' ? '' : 'btn--ghost' }}" href="{{ route('admin.reviews.index', ['tab' => 'tampil']) }}">Tampil di web ({{ $shown }})</a>
</div>
<div class="grid grid--wide">
    <section class="card scroll">
        <table>
            <thead><tr><th>Ulasan</th><th>Bintang</th><th></th></tr></thead>
            <tbody>
            @forelse ($reviews as $r)
                <tr>
                    <td><b>{{ $r->name }}</b>{{ $r->role ? ', '.$r->role : '' }}<div>{{ $r->body }}</div><div class="muted">{{ $r->created_at?->format('d/m/Y H:i') }} · {{ $r->source === 'admin' ? 'dicatat admin' : 'dikirim pembeli' }}</div></td>
                    <td>{{ $r->rating }} / 5</td>
                    <td class="num">
                        <form method="post" action="{{ route('admin.reviews.toggle', $r) }}" style="display:inline">@csrf<button class="btn btn--sm {{ $r->is_published ? 'btn--ghost' : '' }}">{{ $r->is_published ? 'Sembunyikan' : 'Tampilkan' }}</button></form>
                        <form method="post" action="{{ route('admin.reviews.destroy', $r) }}" style="display:inline" onsubmit="return confirm('Hapus ulasan ini?')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Hapus</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">{{ $tab === 'menunggu' ? 'Tidak ada ulasan yang menunggu.' : 'Belum ada ulasan yang tampil.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $reviews->links('pagination.store') }}
    </section>
    <form class="card" method="post" action="{{ route('admin.reviews.store') }}">
        @csrf
        <h2>Catat ulasan asli</h2>
        <p class="hint">Untuk ulasan yang benar-benar diterima lewat jalur lain (WhatsApp, kertas). Tulis sesuai aslinya dan langsung tampil.</p>
        <div class="field"><label for="name">Nama</label><input id="name" type="text" name="name" required maxlength="80"></div>
        <div class="field"><label for="role">Keterangan</label><input id="role" type="text" name="role" maxlength="80"></div>
        <div class="field"><label for="rating">Bintang</label><select id="rating" name="rating">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></div>
        <div class="field"><label for="body">Isi ulasan</label><textarea id="body" name="body" required minlength="10" maxlength="600"></textarea></div>
        <button class="btn">Simpan dan tampilkan</button>
    </form>
</div>
@endsection
