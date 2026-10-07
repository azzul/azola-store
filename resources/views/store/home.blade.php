@extends('layouts.store')

@push('head')
    <script>document.documentElement.classList.add('js')</script>
    <link rel="stylesheet" href="{{ asset('css/home.css') }}?v={{ @filemtime(public_path('css/home.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/home.js') }}?v={{ @filemtime(public_path('js/home.js')) }}" defer></script>
@endpush

@section('content')
    @php
        $waNumber = preg_replace('/\D/', '', (string) config('store.whatsapp'));
        $wa = $waNumber ? 'https://wa.me/'.$waNumber : null;

        // Kardus 3D di hero: [x, y, z, putaran]. Satuan = ukuran kardus. y negatif = ke atas.
        $cubes = [
            [-1, -.5, 0, -18], [0, -.5, 0, 10], [1, -.5, 0, 22],
            [-.5, -1.5, 0, -30], [.5, -1.5, 0, 16], [0, -2.5, 0, -12],
            [-1, -.5, -1, 26], [0, -.5, -1, -8], [1, -.5, -1, 14],
        ];
        $fallbackLabels = ['Stok jujur', 'Harga sama', 'Bayar mudah', 'Ambil gratis', 'QRIS', 'Transfer', 'COD', 'Cek stok', 'Realtime'];
        $labels = $featured->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::limit(trim(\Illuminate\Support\Str::words($n, 2, '')), 13, ''))->values()->all();
        foreach ($fallbackLabels as $f) { if (count($labels) >= count($cubes)) break; $labels[] = $f; }

        $floaters = $shelf->take(3)->values();
        $steps = [
            ['Pilih barang', 'Cari lewat kolom pencarian atau kategori. Label stok menunjukkan barang yang masih ada.'],
            ['Checkout dan bayar', 'Isi nama dan nomor telepon, pilih cara bayar. Stok langsung dipesankan untukmu.'],
            ['Ambil atau kirim', 'Ambil di toko tanpa ongkos kirim, atau minta dikirim ke alamatmu.'],
        ];
    @endphp

    {{-- HERO: kardus 3D yang terbuka dan berputar saat halaman digulir --}}
    <section class="hero3d" data-hero aria-labelledby="hero-title">
        <div class="hero3d__pin">
            <div class="wrap hero3d__grid">
                <div class="hero3d__copy">
                    <h1 id="hero-title"><span class="sr">{{ config('store.name') }}: </span>{{ config('store.tagline') }}</h1>
                    <p class="hero3d__lead">Yang kamu lihat di sini adalah stok yang sama dengan yang ada di rak toko. Pilih, bayar, lalu ambil atau minta dikirim.</p>

                    <form class="search" action="{{ route('shop.index') }}" method="get" role="search">
                        <label class="sr" for="q">Cari produk</label>
                        <input id="q" name="q" type="search" placeholder="Cari nama barang atau kode" autocomplete="off">
                        <button class="btn btn--tag" type="submit">Cari</button>
                    </form>

                    @if ($categories->isNotEmpty())
                        <ul class="chips" aria-label="Kategori">
                            @foreach ($categories->take(6) as $category)
                                <li><a href="{{ $category->url() }}">{{ $category->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif

                    <ul class="hero3d__facts">
                        @if ($stats['products'] > 0)<li><strong>{{ $stats['products'] }}</strong> produk siap dipesan</li>@endif
                        <li>Stok diperbarui otomatis</li>
                        <li>Ambil di toko tanpa ongkir</li>
                    </ul>
                </div>

                <div class="scene" aria-hidden="false">
                    <div class="stage" aria-hidden="true">
                        <div class="floor"></div>
                        @foreach ($cubes as $i => [$x, $y, $z, $r])
                            <div class="cube" style="--x:{{ $x }};--y:{{ $y }};--z:{{ $z }};--r:{{ $r }}">
                                <i class="f f1"><b class="sticker">{{ $labels[$i] }}</b></i>
                                <i class="f f2"><b class="sticker">{{ $labels[($i + 3) % count($labels)] }}</b></i>
                                <i class="f f3"></i><i class="f f4"></i><i class="f f5"></i><i class="f f6"></i>
                            </div>
                        @endforeach
                    </div>

                    @foreach ($floaters as $i => $product)
                        <a class="fl fl--{{ $i + 1 }}" href="{{ $product->url() }}">
                            <span class="fl__name">{{ $product->name }}</span>
                            <span class="tag tag--sm">{{ \App\Support\Rupiah::format($product->price) }}</span>
                            @include('store.partials.stock', ['product' => $product])
                        </a>
                    @endforeach
                </div>
            </div>
            <p class="hero3d__hint" aria-hidden="true"><i></i>Gulir untuk membuka kardus</p>
        </div>
    </section>

    {{-- KENAPA KAMI --}}
    <section class="block why" id="kenapa-kami" aria-labelledby="why-title">
        <div class="wrap why__grid">
            <div class="why__intro">
                <h2 id="why-title">Kenapa belanja di {{ config('store.name') }}</h2>
                <p>{{ config('store.about.headline') }} Semua penjualan di toko dan di website tercatat di satu sistem, jadi tidak ada kejutan di akhir.</p>
                <div class="why__cta">
                    <a class="btn" href="{{ route('shop.index') }}">Mulai belanja</a>
                    <a class="btn btn--ghost" href="{{ route('about') }}">Tentang kami</a>
                </div>
            </div>
            <ul class="why__list">
                @foreach ($why as $item)
                    <li class="why__card" data-tilt>
                        <span class="why__icon">@include('store.partials.icon', ['name' => $item['key']])</span>
                        <h3>{{ $item['title'] }}</h3>
                        <p>{{ $item['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- KATEGORI --}}
    @if ($categories->isNotEmpty())
        <section class="block block--tint cats" aria-labelledby="cats-title">
            <div class="wrap">
                <div class="block__head"><h2 id="cats-title">Belanja per kategori</h2><a href="{{ route('shop.index') }}">Semua produk</a></div>
                <ul class="cats__row">
                    @foreach ($categories as $category)
                        <li>
                            <a class="cat" href="{{ $category->url() }}" style="--h:{{ ($loop->index * 53 + 135) % 360 }}">
                                <span class="mini" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                                <span class="cat__name">{{ $category->name }}</span>
                                <span class="cat__n">{{ $category->online_products_count }} produk</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- PRODUK PILIHAN --}}
    @if ($featured->isNotEmpty())
        <section class="block" aria-labelledby="featured-title">
            <div class="wrap">
                <div class="block__head">
                    <h2 id="featured-title">Produk pilihan</h2>
                    <a href="{{ route('shop.index') }}">Lihat semua produk</a>
                </div>
                <div class="grid">
                    @foreach ($featured as $product)
                        @include('store.partials.card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CARA BELANJA: kartu bertumpuk 3D yang berganti saat digulir --}}
    <section class="how" id="cara-belanja" data-how aria-labelledby="how-title">
        <div class="how__pin">
            <div class="wrap how__grid">
                <div class="how__copy">
                    <h2 id="how-title">Cara belanja</h2>
                    <ol class="how__nav">
                        @foreach ($steps as $i => [$title, $text])
                            <li data-step="{{ $i }}" @if ($i === 0) aria-current="step" @endif>
                                <h3>{{ $title }}</h3>
                                <p>{{ $text }}</p>
                            </li>
                        @endforeach
                    </ol>
                    <div class="how__bar" aria-hidden="true"><i></i></div>
                </div>

                <div class="how__stage" aria-hidden="true">
                    <div class="hcards">
                        <article class="hcard" data-card="0">
                            <p class="hcard__no">1</p>
                            <p class="hcard__title">Pilih barang</p>
                            <div class="mock mock__search">Cari nama barang atau kode</div>
                            @foreach ($featured->take(2) as $product)
                                <div class="mock mock__row">
                                    <span>{{ $product->name }}</span>
                                    <span class="tag tag--sm">{{ \App\Support\Rupiah::format($product->price) }}</span>
                                    @include('store.partials.stock', ['product' => $product])
                                </div>
                            @endforeach
                            @if ($featured->isEmpty())<div class="mock mock__row"><span>Nama barang</span><span class="tag tag--sm">Rp0</span></div>@endif
                        </article>

                        <article class="hcard" data-card="1">
                            <p class="hcard__no">2</p>
                            <p class="hcard__title">Checkout dan bayar</p>
                            <div class="mock mock__field">Nama</div>
                            <div class="mock mock__field">Nomor telepon</div>
                            <div class="mock__pills">
                                @foreach (config('store.web_payment_methods') as $label)<span>{{ $label }}</span>@endforeach
                            </div>
                        </article>

                        <article class="hcard" data-card="2">
                            <p class="hcard__no">3</p>
                            <p class="hcard__title">Ambil atau kirim</p>
                            <div class="mock mock__opt"><strong>Ambil di toko</strong><span>Gratis, tanpa ongkos kirim</span></div>
                            <div class="mock mock__opt"><strong>Dikirim ke alamat</strong><span>Ongkos {{ \App\Support\Rupiah::format(config('store.shipping.flat')) }}@if (config('store.shipping.free_over')), gratis di atas {{ \App\Support\Rupiah::format(config('store.shipping.free_over')) }}@endif</span></div>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SATU STOK: lapisan isometrik yang terbuka saat digulir --}}
    <section class="block block--tint sync" data-sync aria-labelledby="sync-title">
        <div class="wrap sync__grid">
            <div class="sync__copy">
                <h2 id="sync-title">Satu stok untuk web dan toko</h2>
                <p>Kasir di toko, aplikasi Android, dan website ini memakai satu database. Saat barang terjual di kasir, jumlahnya di halaman ini ikut berkurang dalam hitungan detik, jadi kamu tidak memesan barang yang sebenarnya sudah habis.</p>
                <div class="split__facts">
                    <p><strong>Harga dari sistem kasir.</strong> Harga di web selalu sama dengan harga di toko.</p>
                    <p><strong>Stok dipesankan saat checkout.</strong> Barang di keranjang yang sudah dibayar tidak diambil orang lain.</p>
                </div>
            </div>
            <div class="sync__scene" aria-hidden="true">
                <div class="iso">
                    <div class="slab slab--base"><b>Satu database stok</b></div>
                    <div class="rod rod--a"></div><div class="rod rod--b"></div>
                    <div class="slab slab--pos" style="--i:1"><b>Kasir toko</b></div>
                    <div class="slab slab--app" style="--i:2"><b>Aplikasi Android</b></div>
                    <div class="slab slab--web" style="--i:3"><b>Website</b></div>
                    <div class="pulse"><i>stok -1</i></div>
                </div>
            </div>
        </div>
    </section>

    {{-- ULASAN --}}
    @if (($homeReviews ?? collect())->isNotEmpty())
        <section class="block reviews3d" aria-labelledby="reviews-title">
            <div class="wrap">
                <div class="block__head"><h2 id="reviews-title">Kata pelanggan</h2><a href="{{ route('reviews') }}">Baca semua ulasan</a></div>
                <div class="reviews3d__grid">
                    <div class="reviews3d__sum">
                        <p class="reviews3d__avg">{{ number_format($reviewSummary['average'], 1, ',', '') }}<small>/5</small></p>
                        @include('store.partials.stars', ['rating' => (int) round($reviewSummary['average'])])
                        <p class="muted">dari {{ $reviewSummary['count'] }} ulasan</p>
                        <ul class="bars">
                            @foreach ($reviewSummary['bars'] as $star => $n)
                                <li><span>{{ $star }}</span><i style="--w:{{ $reviewSummary['count'] ? round($n / $reviewSummary['count'] * 100) : 0 }}%"></i><span>{{ $n }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="reviews3d__row">
                        @foreach ($homeReviews as $review)
                            <div class="rv" data-tilt>@include('store.partials.review', ['review' => $review])</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @else
        <section class="block reviews3d reviews3d--empty" aria-labelledby="reviews-title">
            <div class="wrap">
                <div class="invite">
                    <div>
                        <h2 id="reviews-title">Ulasan pelanggan</h2>
                        <p>Belum ada ulasan yang ditampilkan. Sudah pernah belanja di sini? Ceritakan pengalamanmu. Ulasan tampil setelah kami periksa.</p>
                    </div>
                    <a class="btn" href="{{ route('reviews') }}#tulis">Tulis ulasan</a>
                </div>
            </div>
        </section>
    @endif

    {{-- KLIEN --}}
    @if (($homeClients ?? collect())->isNotEmpty())
        <section class="block block--tint" aria-labelledby="clients-title">
            <div class="wrap">
                <div class="block__head"><h2 id="clients-title">Dipercaya oleh</h2><a href="{{ route('clients') }}">Lihat semua klien</a></div>
                @if ($homeClients->count() >= 5)
                    <div class="marq">
                        <div class="marq__track">
                            <div class="marq__set">@foreach ($homeClients as $client)@include('store.partials.client', ['client' => $client])@endforeach</div>
                            <div class="marq__set" inert aria-hidden="true">@foreach ($homeClients as $client)@include('store.partials.client', ['client' => $client])@endforeach</div>
                        </div>
                    </div>
                @else
                    <div class="clients">@foreach ($homeClients as $client)@include('store.partials.client', ['client' => $client])@endforeach</div>
                @endif
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    <section class="block faq3d" id="faq" aria-labelledby="faq-title">
        <div class="wrap faq3d__grid">
            <div>
                <h2 id="faq-title">Pertanyaan umum</h2>
                <p class="muted">Jawaban singkat sebelum kamu belanja.</p>
                @if ($wa)<p><a class="btn btn--ghost" href="{{ $wa }}" rel="noopener">Tanya lewat WhatsApp</a></p>@endif
            </div>
            <div class="faq">
                @foreach ($faq as $item)
                    <details>
                        <summary>{{ $item['q'] }}</summary>
                        <p>{{ $item['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- AJAKAN AKHIR --}}
    <section class="visit" aria-labelledby="visit-title">
        <div class="wrap visit__grid">
            <div>
                <h2 id="visit-title">Mampir ke toko atau chat dulu</h2>
                @if (config('store.address'))<p>{{ config('store.address') }}</p>@endif
                <div class="visit__cta">
                    <a class="btn btn--tag" href="{{ route('shop.index') }}">Belanja sekarang</a>
                    @if ($wa)<a class="btn btn--light" href="{{ $wa }}" rel="noopener">Chat WhatsApp</a>@endif
                    @if (config('store.map_url'))<a class="btn btn--light" href="{{ config('store.map_url') }}" rel="noopener" target="_blank">Lihat peta</a>@endif
                </div>
            </div>
            <dl class="visit__hours">
                @foreach (config('store.hours') as [$day, $time])
                    <div><dt>{{ $day }}</dt><dd>{{ $time }}</dd></div>
                @endforeach
            </dl>
        </div>
    </section>
@endsection
