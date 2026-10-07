@php
    $seo = $seo ?? \App\Support\Seo::page();
    $cartCount = app(\App\Support\Cart::class)->count();
    $hex = fn ($value, $fallback) => preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $value) ? $value : $fallback;
    $brand = $hex(config('store.theme.brand'), '#0F5C46');
    $accent = $hex(config('store.theme.accent'), '#FFD43B');
    $store = config('store.name');
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta name="robots" content="{{ $seo['robots'] }}">
    <meta name="theme-color" content="{{ $brand }}">
    <meta property="og:site_name" content="{{ $store }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $seo['type'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    @if ($seo['image'])
        <meta property="og:image" content="{{ url($seo['image']) }}">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <link rel="preload" href="{{ asset('fonts/bricolage-grotesque-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <style>:root{--brand:{{ $brand }};--tag:{{ $accent }}}</style>
    <link rel="stylesheet" href="{{ asset('css/store.css') }}?v={{ @filemtime(public_path('css/store.css')) }}">
    @stack('head')
    @foreach ($seo['jsonld'] as $ld)
        <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
</head>
<body>
<a class="skip" href="#isi">Langsung ke konten</a>

<header class="top">
    <div class="wrap top__row">
        <a class="brand" href="{{ route('home') }}">{{ $store }}</a>
        @php $menu = [[route('shop.index'), 'Produk'], [route('about'), 'Tentang kami'], [route('reviews'), 'Ulasan'], [route('clients'), 'Klien'], [route('contact'), 'Kontak']]; @endphp
        <nav class="top__nav" aria-label="Menu utama">
            @foreach ($menu as [$href, $label])<a href="{{ $href }}" @if (url()->current() === $href) aria-current="page" @endif>{{ $label }}</a>@endforeach
        </nav>
        <a class="cartlink" href="{{ route('cart.index') }}">
            Keranjang
            @if ($cartCount > 0)<span class="cartlink__n" aria-label="{{ $cartCount }} jenis barang">{{ $cartCount }}</span>@endif
        </a>
        <details class="menu">
            <summary aria-label="Buka menu"><span aria-hidden="true">Menu</span></summary>
            <nav class="menu__panel" aria-label="Menu seluler">
                @foreach ($menu as [$href, $label])<a href="{{ $href }}">{{ $label }}</a>@endforeach
            </nav>
        </details>
    </div>
</header>

@if (session('added'))
    <div class="notice" role="status"><div class="wrap">{{ session('added') }} <a href="{{ route('cart.index') }}">Lihat keranjang</a></div></div>
@endif

<main id="isi">
    @yield('content')
</main>

<footer class="foot">
    <div class="wrap foot__grid">
        <div>
            <p class="foot__name">{{ $store }}</p>
            <p>{{ config('store.tagline') }}</p>
        </div>
        <div>
            <p class="foot__h">Kontak</p>
            @if (config('store.address'))<p>{{ config('store.address') }}</p>@endif
            @if (config('store.whatsapp'))<p><a href="https://wa.me/{{ preg_replace('/\D/', '', config('store.whatsapp')) }}" rel="noopener">WhatsApp {{ config('store.whatsapp') }}</a></p>@endif
            @if (config('store.email'))<p><a href="mailto:{{ config('store.email') }}">{{ config('store.email') }}</a></p>@endif
        </div>
        <div>
            <p class="foot__h">Informasi</p>
            <p><a href="{{ route('shop.index') }}">Semua produk</a></p>
            <p><a href="{{ route('about') }}">Tentang kami</a></p>
            <p><a href="{{ route('faq') }}">Pertanyaan umum</a></p>
            <p><a href="{{ route('returns') }}">Pengembalian dan pembatalan</a></p>
            <p><a href="{{ route('terms') }}">Syarat dan ketentuan</a></p>
            <p><a href="{{ route('privacy') }}">Kebijakan privasi</a></p>
        </div>
    </div>
    <div class="wrap foot__copy">&copy; {{ now()->year }} {{ $store }}</div>
</footer>

<script src="{{ asset('js/live-stock.js') }}?v={{ @filemtime(public_path('js/live-stock.js')) }}" defer
        data-feed="{{ url('/api/v1/public/stock-feed') }}" data-cursor="{{ $stockCursor ?? 0 }}"></script>
@stack('scripts')
</body>
</html>
