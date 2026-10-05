@php
    $hex = fn ($v, $f) => preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $v) ? $v : $f;
    $brand = $hex(config('store.theme.brand'), '#0F5C46');
    $accent = $hex(config('store.theme.accent'), '#FFD43B');
    $unread = \App\Models\ContactMessage::whereNull('read_at')->count();
    $pendingReviews = \App\Models\Testimonial::where('is_published', false)->count();
    $nav = [
        ['admin.dashboard', 'Ringkasan', 'admin'],
        ['admin.stock', 'Stok realtime', 'admin/stok*'],
        ['admin.orders.index', 'Pesanan', 'admin/pesanan*'],
        ['admin.products.index', 'Produk', 'admin/produk*'],
        ['admin.categories.index', 'Kategori', 'admin/kategori*'],
        ['admin.journals.index', 'Jurnal', 'admin/jurnal*'],
        ['admin.report', 'Laporan', 'admin/laporan*'],
        ['admin.reconcile', 'Rekonsiliasi', 'admin/rekonsiliasi*'],
        ['admin.messages.index', 'Pesan masuk'.($unread ? " ({$unread})" : ''), 'admin/pesan*'],
        ['admin.reviews.index', 'Ulasan'.($pendingReviews ? " ({$pendingReviews})" : ''), 'admin/ulasan*'],
        ['admin.clients.index', 'Klien', 'admin/klien*'],
        ['admin.devices.index', 'Perangkat POS', 'admin/perangkat*'],
    ];
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Admin') - {{ config('store.name') }}</title>
    <style>:root{--brand:{{ $brand }};--tag:{{ $accent }}}</style>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="shell">
    <aside class="side">
        <a class="side__brand" href="{{ route('admin.dashboard') }}">{{ config('store.name') }}<small>Admin</small></a>
        <nav aria-label="Menu admin">
            @foreach ($nav as [$route, $label, $pattern])
                <a href="{{ route($route) }}" @class(['is-on' => request()->is($pattern)])>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="side__foot">
            <a href="{{ route('home') }}" target="_blank" rel="noopener">Lihat toko</a>
            <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="linkbtn">Keluar ({{ auth()->user()->name }})</button></form>
        </div>
    </aside>

    <main class="main" id="isi">
        <header class="head">
            <h1>@yield('heading')</h1>
            <div class="head__actions">@yield('actions')</div>
        </header>

        @if (session('ok'))<div class="flash flash--ok" role="status">{{ session('ok') }}</div>@endif
        @if ($errors->any())
            <div class="flash flash--err" role="alert">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
