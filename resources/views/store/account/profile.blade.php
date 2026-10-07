@extends('layouts.store')
@section('content')
<div class="wrap page page--narrow">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Akun saya', route('account.home')], ['Profil', route('account.profile')]]])
    <header class="page__head"><h1>Profil dan alamat</h1><p class="page__lead">Data ini terisi otomatis saat checkout.</p></header>
    @if ($errors->any())<div class="alert" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form class="card-form" method="post" action="{{ route('account.profile.update') }}">
        @csrf @method('PUT')
        <h2 class="h-sm">Data diri</h2>
        <div class="field"><label for="name">Nama</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120"></div>
        <div class="field"><label for="email">Email</label><input id="email" value="{{ $user->email }}" disabled></div>
        <div class="field"><label for="phone">Nomor WhatsApp</label><input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30"></div>
        <div class="field"><label for="address">Alamat pengiriman</label><textarea id="address" name="address" rows="3" maxlength="500">{{ old('address', $user->address) }}</textarea></div>
        <button class="btn" type="submit">Simpan profil</button>
    </form>

    <form class="card-form" method="post" action="{{ route('account.password') }}">
        @csrf @method('PUT')
        <h2 class="h-sm">Ganti password</h2>
        <div class="field"><label for="current_password">Password saat ini</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password"></div>
        <div class="field"><label for="new_password">Password baru (minimal 8 karakter)</label><input id="new_password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
        <div class="field"><label for="new_password_confirmation">Ulangi password baru</label><input id="new_password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn--ghost" type="submit">Ganti password</button>
    </form>
</div>
@endsection
