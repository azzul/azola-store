@extends('layouts.admin')
@section('title', 'Klien')
@section('heading', 'Klien dan mitra')
@section('content')
<div class="grid grid--wide">
    <section class="card scroll">
        <table>
            <thead><tr><th></th><th>Klien</th><th>Urutan</th><th></th></tr></thead>
            <tbody>
            @forelse ($clients as $c)
                <tr>
                    <td>@if ($c->logoUrl())<img class="thumb" src="{{ $c->logoUrl() }}" alt="" loading="lazy" style="object-fit:contain">@else<span class="thumb">{{ $c->initials() }}</span>@endif</td>
                    <td>{{ $c->name }}<div class="muted">{{ $c->note }}{{ $c->url ? ' · '.$c->url : '' }}</div></td>
                    <td>{{ $c->sort_order }}</td>
                    <td class="num">
                        <form method="post" action="{{ route('admin.clients.toggle', $c) }}" style="display:inline">@csrf<button class="btn btn--sm {{ $c->is_published ? 'btn--ghost' : '' }}">{{ $c->is_published ? 'Sembunyikan' : 'Tampilkan' }}</button></form>
                        <form method="post" action="{{ route('admin.clients.destroy', $c) }}" style="display:inline" onsubmit="return confirm('Hapus klien ini?')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Hapus</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Belum ada klien. Tambahkan dari form di samping.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <form class="card" method="post" enctype="multipart/form-data" action="{{ route('admin.clients.store') }}">
        @csrf
        <h2>Tambah klien</h2>
        <div class="field"><label for="name">Nama</label><input id="name" type="text" name="name" required maxlength="120"></div>
        <div class="field"><label for="url">Website (opsional)</label><input id="url" type="text" name="url" placeholder="https://..." maxlength="255"></div>
        <div class="field"><label for="note">Keterangan singkat</label><input id="note" type="text" name="note" maxlength="160"></div>
        <div class="field"><label for="logo">Logo (maks. 1 MB)</label><input id="logo" type="file" name="logo" accept="image/*"></div>
        <div class="field"><label for="sort_order">Urutan</label><input id="sort_order" type="number" name="sort_order" min="0" value="0"></div>
        <button class="btn">Tambah klien</button>
        <p class="hint">Tampilkan hanya klien yang sudah setuju namanya dipasang.</p>
    </form>
</div>
@endsection
