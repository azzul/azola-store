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
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/store.css') }}?v={{ @filemtime(public_path('css/store.css')) }}">
    @stack('head')
    @foreach ($seo['jsonld'] as $ld)
        <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
</head>
<body>
@php
    $user = auth()->user();
    $waNumber = preg_replace('/\D/', '', (string) config('store.whatsapp'));
    $waLink = $waNumber ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode('Halo '.$store.', saya mau tanya...') : null;
    $has = fn ($name) => \Illuminate\Support\Facades\Route::has($name);
    $menu = array_values(array_filter([
        [route('shop.index'), 'Produk', 'produk*', 'kategori*', 'etalase*'],
        $has('pricelist') ? [route('pricelist'), 'Pricelist', 'pricelist*'] : null,
        $has('articles.index') ? [route('articles.index'), 'Artikel', 'artikel*'] : null,
        [route('about'), 'Tentang kami', 'tentang-kami'],
        [route('contact'), 'Kontak', 'kontak'],
    ]));
    $more = [[route('reviews'), 'Ulasan'], [route('clients'), 'Klien'], [route('faq'), 'Pertanyaan umum']];
    $socials = array_filter([
        'instagram' => config('store.instagram') ? 'https://instagram.com/'.config('store.instagram') : null,
        'facebook' => config('store.facebook') ?: null,
        'tiktok' => config('store.tiktok') ? 'https://tiktok.com/@'.config('store.tiktok') : null,
        'youtube' => config('store.youtube') ?: null,
    ]);
@endphp
<a class="skip" href="#isi">Langsung ke konten</a>

<header class="top">
    <div class="wrap top__row">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $store }}, ke beranda">
            @include('store.partials.logo')
            <span class="brand__name">{{ $store }}</span>
        </a>
        <nav class="top__nav" aria-label="Menu utama">
            @foreach ($menu as $item)
                <a href="{{ $item[0] }}" @if (request()->is(...array_slice($item, 2))) aria-current="page" @endif>{{ $item[1] }}</a>
            @endforeach
        </nav>

        <div class="tools">
            <a class="tool" href="{{ route('account.orders') }}" title="Pesanan saya" aria-label="Pesanan saya">@include('store.partials.icon', ['name' => 'receipt', 'size' => 22])<span class="tool__label">Pesanan</span></a>

            <details class="tool tool--acct" data-dropdown>
                <summary title="Akun" aria-label="{{ $user ? 'Akun '.$user->name : 'Masuk atau daftar' }}">@include('store.partials.icon', ['name' => 'user', 'size' => 22])<span class="tool__label">{{ $user ? \Illuminate\Support\Str::before($user->name, ' ') : 'Masuk' }}</span></summary>
                <div class="acct">
                    @if ($user)
                        <p class="acct__who"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></p>
                        <a href="{{ route('account.home') }}">Akun saya</a>
                        <a href="{{ route('account.orders') }}">Pesanan saya</a>
                        <a href="{{ route('account.profile') }}">Profil dan alamat</a>
                        @if ($user->isAdmin())<a href="{{ route('admin.dashboard') }}">Dashboard admin</a>@endif
                        <form method="post" action="{{ route('account.logout') }}">@csrf<button type="submit">Keluar</button></form>
                    @else
                        <p class="acct__who"><strong>Halo, pembeli</strong><small>Masuk untuk melihat pesanan dan mengisi alamat otomatis.</small></p>
                        <a class="acct__primary" href="{{ route('account.login') }}">Masuk</a>
                        <a href="{{ route('account.register') }}">Daftar akun baru</a>
                    @endif
                </div>
            </details>

            <a class="tool tool--cart" href="{{ route('cart.index') }}" title="Keranjang" aria-label="Keranjang, {{ $cartCount }} jenis barang">
                @include('store.partials.icon', ['name' => 'cart', 'size' => 22])
                @if ($cartCount > 0)<span class="tool__n">{{ $cartCount }}</span>@endif
                <span class="tool__label">Keranjang</span>
            </a>
        </div>

        <details class="menu" data-dropdown>
            <summary aria-label="Buka menu">@include('store.partials.icon', ['name' => 'menu', 'size' => 22])</summary>
            <nav class="menu__panel" aria-label="Menu seluler">
                @foreach ($menu as $item)<a href="{{ $item[0] }}">{{ $item[1] }}</a>@endforeach
                @foreach ($more as [$href, $label])<a href="{{ $href }}">{{ $label }}</a>@endforeach
            </nav>
        </details>
    </div>
</header>

@if (session('added'))
    <div class="notice" role="status"><div class="wrap">{{ session('added') }} <a href="{{ route('cart.index') }}">Lihat keranjang</a></div></div>
@endif
@if (session('ok'))
    <div class="notice" role="status"><div class="wrap">{{ session('ok') }}</div></div>
@endif

<main id="isi">
    @yield('content')
</main>

<footer class="foot">
    <div class="wrap foot__grid">
        <div class="foot__about">
            <a class="brand brand--foot" href="{{ route('home') }}" aria-label="{{ $store }}, ke beranda">@include('store.partials.logo')<span class="brand__name">{{ $store }}</span></a>
            <p>{{ config('store.tagline') }}</p>
            @if ($socials || $waLink)
                <ul class="social" aria-label="Media sosial">
                    @if ($waLink)<li><a href="{{ $waLink }}" rel="noopener" aria-label="WhatsApp">@include('store.partials.icon', ['name' => 'wa', 'size' => 20])</a></li>@endif
                    @foreach ($socials as $net => $url)
                        <li><a href="{{ $url }}" target="_blank" rel="noopener me" aria-label="{{ ucfirst($net) }}">@include('store.partials.icon', ['name' => $net, 'size' => 20])</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div>
            <p class="foot__h">Belanja</p>
            <p><a href="{{ route('shop.index') }}">Semua produk</a></p>
            @if ($has('pricelist'))<p><a href="{{ route('pricelist') }}">Pricelist</a></p>@endif
            @if ($has('articles.index'))<p><a href="{{ route('articles.index') }}">Artikel</a></p>@endif
            <p><a href="{{ route('cart.index') }}">Keranjang</a></p>
            <p><a href="{{ route('account.orders') }}">Pesanan saya</a></p>
        </div>
        <div>
            <p class="foot__h">Tentang</p>
            <p><a href="{{ route('about') }}">Tentang kami</a></p>
            <p><a href="{{ route('reviews') }}">Ulasan pelanggan</a></p>
            <p><a href="{{ route('clients') }}">Klien</a></p>
            <p><a href="{{ route('faq') }}">Pertanyaan umum</a></p>
            <p><a href="{{ route('returns') }}">Pengembalian dan pembatalan</a></p>
            <p><a href="{{ route('terms') }}">Syarat dan ketentuan</a></p>
            <p><a href="{{ route('privacy') }}">Kebijakan privasi</a></p>
        </div>
        <div class="foot__contact">
            <p class="foot__h">Hubungi kami</p>
            @if (config('store.address'))<p class="foot__line">@include('store.partials.icon', ['name' => 'pin', 'size' => 18])<span>{{ config('store.address') }}</span></p>@endif
            @if (config('store.whatsapp'))<p class="foot__line">@include('store.partials.icon', ['name' => 'phone', 'size' => 18])<a href="https://wa.me/{{ $waNumber }}" rel="noopener">{{ config('store.whatsapp') }}</a></p>@endif
            @if (config('store.email'))<p class="foot__line">@include('store.partials.icon', ['name' => 'mail', 'size' => 18])<a href="mailto:{{ config('store.email') }}">{{ config('store.email') }}</a></p>@endif
            @if (config('store.hours'))
                <p class="foot__line foot__line--hours">@foreach (config('store.hours') as [$day, $time])<span>{{ $day }}: {{ $time }}</span>@endforeach</p>
            @endif
        </div>
    </div>
    <div class="wrap foot__copy">
        <span>&copy; {{ now()->year }} {{ $store }}. Semua hak dilindungi.</span>
        <span class="foot__pay">Pembayaran: @foreach (config('store.web_payment_methods') as $label)<em>{{ $label }}</em>@endforeach</span>
    </div>
</footer>

{{-- Tombol WhatsApp melayang (layar lebar). Di HP, WhatsApp ada di bilah bawah. --}}
@if ($waLink)
    <a class="wafab" href="{{ $waLink }}" rel="noopener" target="_blank">
        @include('store.partials.icon', ['name' => 'wa', 'size' => 24])
        <span>Hubungi kami</span>
    </a>
@endif

{{-- Bilah navigasi bawah (HP) --}}
<nav class="bnav" aria-label="Navigasi cepat">
    <a href="{{ route('home') }}" @class(['is-on' => request()->is('/')])>@include('store.partials.icon', ['name' => 'home', 'size' => 22])<span>Beranda</span></a>
    <a href="{{ route('shop.index') }}" @class(['is-on' => request()->is('produk*', 'kategori*', 'etalase*')])>@include('store.partials.icon', ['name' => 'grid', 'size' => 22])<span>Produk</span></a>
    @if ($waLink)
        <a class="bnav__wa" href="{{ $waLink }}" rel="noopener" target="_blank" aria-label="Chat WhatsApp">@include('store.partials.icon', ['name' => 'wa', 'size' => 26])<span>Chat</span></a>
    @else
        <a class="bnav__wa" href="{{ route('contact') }}" aria-label="Kontak">@include('store.partials.icon', ['name' => 'help', 'size' => 26])<span>Kontak</span></a>
    @endif
    <a href="{{ route('cart.index') }}" @class(['is-on' => request()->is('keranjang*', 'checkout*')])>
        <span class="bnav__ico">@include('store.partials.icon', ['name' => 'cart', 'size' => 22])@if ($cartCount > 0)<b>{{ $cartCount }}</b>@endif</span><span>Keranjang</span>
    </a>
    <a href="{{ $user ? route('account.home') : route('account.login') }}" @class(['is-on' => request()->is('akun*', 'masuk', 'daftar')])>@include('store.partials.icon', ['name' => 'user', 'size' => 22])<span>{{ $user ? 'Akun' : 'Masuk' }}</span></a>
</nav>

<script src="{{ asset('js/chrome.js') }}?v={{ @filemtime(public_path('js/chrome.js')) }}" defer></script>
<script src="{{ asset('js/live-stock.js') }}?v={{ @filemtime(public_path('js/live-stock.js')) }}" defer
        data-feed="{{ url('/api/v1/public/stock-feed') }}" data-cursor="{{ $stockCursor ?? 0 }}"></script>
@stack('scripts')
</body>
</html>
