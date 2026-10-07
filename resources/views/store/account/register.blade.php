@extends('layouts.store')
@section('content')
<div class="wrap page auth">
    <div class="auth__box">
        <h1>Daftar akun</h1>
        <p class="muted">Gratis. Pesananmu tersimpan dan mudah dilacak.</p>
        <form class="card-form" method="post" action="{{ route('account.register.store') }}">
            @csrf
            @if ($errors->any())<div class="alert" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            <div class="hp" aria-hidden="true"><label>Jangan diisi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="field"><label for="name">Nama lengkap</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" autofocus></div>
            <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="160" autocomplete="email"></div>
            <div class="field"><label for="phone">Nomor WhatsApp (boleh kosong)</label><input id="phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel"></div>
            <div class="field"><label for="password">Password (minimal 8 karakter)</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
            <div class="field"><label for="password_confirmation">Ulangi password</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
            <button class="btn btn--block" type="submit">Buat akun</button>
        </form>
        <p class="auth__alt">Sudah punya akun? <a href="{{ route('account.login') }}">Masuk</a></p>
    </div>
</div>
@endsection
