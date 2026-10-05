<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Masuk admin - {{ config('store.name') }}</title>
    @php $b = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) config('store.theme.brand')) ? config('store.theme.brand') : '#0F5C46'; $a = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) config('store.theme.accent')) ? config('store.theme.accent') : '#FFD43B'; @endphp
    <style>:root{--brand:{{ $b }};--tag:{{ $a }}}</style>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="login">
<form class="card" method="post" action="{{ route('admin.login.store') }}">
    @csrf
    <h1>{{ config('store.name') }}</h1>
    <p class="muted">Masuk untuk mengelola toko.</p>
    @if ($errors->any())<div class="flash flash--err" role="alert">{{ $errors->first() }}</div>@endif
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
    <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
    <p></p>
    <button class="btn" style="width:100%">Masuk</button>
</form>
</body>
</html>
