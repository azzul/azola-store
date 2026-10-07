@extends('layouts.store')
@section('content')
<div class="wrap page auth">
    <div class="auth__box">
        <h1>Masuk</h1>
        <p class="muted">Lihat pesananmu dan checkout lebih cepat dengan alamat tersimpan.</p>
        <form class="card-form" method="post" action="{{ route('account.login.store') }}">
            @csrf
            @if ($errors->any())<div class="alert" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
            <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></div>
            <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
            <label class="choice choice--plain"><input type="checkbox" name="remember" value="1"> <span>Ingat saya di perangkat ini</span></label>
            <button class="btn btn--block" type="submit">Masuk</button>
        </form>
        <p class="auth__alt">Belum punya akun? <a href="{{ route('account.register') }}">Daftar sekarang</a></p>
        <p class="auth__alt muted">Belanja tanpa akun juga bisa. Cukup isi data saat checkout.</p>
    </div>
</div>
@endsection
