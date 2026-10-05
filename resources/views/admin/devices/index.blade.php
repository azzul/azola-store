@extends('layouts.admin')
@section('title', 'Perangkat POS')
@section('heading', 'Perangkat Azola Pos')
@section('content')
@if (session('new_token'))
    <div class="token" role="status">
        <b>Token baru. Salin sekarang, tidak akan ditampilkan lagi.</b>
        <code>{{ session('new_token') }}</code>
        <span class="muted">Masukkan token ini di pengaturan Azola Pos, atau kirim sebagai header <code style="display:inline;padding:0 .2rem">Authorization: Bearer ...</code></span>
    </div>
@endif
<div class="grid grid--2" style="align-items:start">
    <section class="card scroll">
        <h2>Token aktif dan riwayat</h2>
        <table>
            <thead><tr><th>Perangkat</th><th>Terakhir dipakai</th><th></th></tr></thead>
            <tbody>
            @forelse ($tokens as $t)
                <tr>
                    <td>{{ $t->name }}<div class="muted">{{ $t->device_type === 'android' ? 'Android' : 'Desktop' }} · {{ $t->user?->name }}</div></td>
                    <td>{{ $t->last_used_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                    <td class="num">
                        @if ($t->revoked_at)<span class="badge badge--bad">dicabut</span>
                        @else<form method="post" action="{{ route('admin.devices.revoke', $t) }}" onsubmit="return confirm('Cabut token ini?')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Cabut</button></form>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Belum ada perangkat terhubung.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <form class="card" method="post" action="{{ route('admin.devices.store') }}">
        @csrf
        <h2>Hubungkan perangkat</h2>
        <div class="field"><label for="name">Nama perangkat</label><input id="name" type="text" name="name" placeholder="Kasir depan" required maxlength="80"></div>
        <div class="field"><label for="device_type">Jenis</label><select id="device_type" name="device_type"><option value="desktop">Azola Pos Desktop</option><option value="android">Azola Pos Android</option></select></div>
        <button class="btn">Buat token</button>
        <p class="hint">Token dipakai di akun admin ini. Cabut token kalau perangkat hilang.</p>
    </form>
</div>
@endsection
