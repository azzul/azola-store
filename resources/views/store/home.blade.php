@extends('layouts.store')

@section('content')
    <section class="hero">
        <div class="wrap hero__grid">
            <div class="hero__copy">
                <h1>{{ config('store.name') }}</h1>
                <p class="hero__lead">{{ config('store.tagline') }} Yang kamu lihat di sini adalah stok yang sama dengan yang ada di rak toko.</p>

                <form class="search" action="{{ route('shop.index') }}" method="get" role="search">
                    <label class="sr" for="q">Cari produk</label>
                    <input id="q" name="q" type="search" placeholder="Cari nama barang atau kode" autocomplete="off">
                    <button class="btn" type="submit">Cari</button>
                </form>

                @if ($categories->isNotEmpty())
                    <ul class="chips" aria-label="Kategori">
                        @foreach ($categories as $category)
                            <li><a href="{{ $category->url() }}">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <aside class="shelf" aria-labelledby="shelf-title">
                <div class="shelf__head">
                    <h2 id="shelf-title">Stok saat ini</h2>
                    <span class="live"><i aria-hidden="true"></i>diperbarui otomatis</span>
                </div>
                @forelse ($shelf as $product)
                    <a class="shelf__row" href="{{ $product->url() }}">
                        <span class="shelf__name">{{ $product->name }}</span>
                        <span class="tag tag--sm">{{ \App\Support\Rupiah::format($product->price) }}</span>
                        @include('store.partials.stock', ['product' => $product])
                    </a>
                @empty
                    <p class="shelf__empty">Belum ada produk. Tambahkan produk dari dashboard admin.</p>
                @endforelse
            </aside>
        </div>
    </section>

    @if ($featured->isNotEmpty())
        <section class="block">
            <div class="wrap">
                <div class="block__head">
                    <h2>Produk pilihan</h2>
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

    @if ($categories->isNotEmpty())
        <section class="block block--tint">
            <div class="wrap">
                <h2>Belanja per kategori</h2>
                <ul class="catlist">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ $category->url() }}">
                                <span>{{ $category->name }}</span>
                                <span class="catlist__n">{{ $category->online_products_count }} produk</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="block" id="cara-belanja">
        <div class="wrap">
            <h2>Cara belanja</h2>
            <ol class="steps">
                <li><h3>Pilih barang</h3><p>Cari lewat kolom pencarian atau kategori. Label stok menunjukkan barang yang masih ada.</p></li>
                <li><h3>Checkout dan bayar</h3><p>Isi nama dan nomor telepon, pilih transfer, QRIS, atau bayar di tempat. Stok langsung dipesankan untukmu.</p></li>
                <li><h3>Ambil atau kirim</h3><p>Ambil di toko tanpa ongkos kirim, atau minta dikirim ke alamatmu.</p></li>
            </ol>
        </div>
    </section>

    <section class="block block--tint">
        <div class="wrap split">
            <div>
                <h2>Satu stok untuk web dan toko</h2>
                <p>Kasir di toko, aplikasi Android, dan website ini memakai satu database. Saat barang terjual di kasir, jumlahnya di halaman ini ikut berkurang dalam hitungan detik, jadi kamu tidak memesan barang yang sebenarnya sudah habis.</p>
            </div>
            <div class="split__facts">
                <p><strong>Harga dari sistem kasir.</strong> Harga di web selalu sama dengan harga di toko.</p>
                <p><strong>Stok dipesankan saat checkout.</strong> Barang di keranjang yang sudah dibayar tidak diambil orang lain.</p>
            </div>
        </div>
    </section>

    @if (($homeReviews ?? collect())->isNotEmpty())
        <section class="block">
            <div class="wrap">
                <div class="block__head"><h2>Kata pelanggan</h2><a href="{{ route('reviews') }}">Baca semua ulasan</a></div>
                <div class="reviews reviews--row">@foreach ($homeReviews as $review)@include('store.partials.review', ['review' => $review])@endforeach</div>
            </div>
        </section>
    @endif

    @if (($homeClients ?? collect())->isNotEmpty())
        <section class="block block--tint">
            <div class="wrap">
                <div class="block__head"><h2>Dipercaya oleh</h2><a href="{{ route('clients') }}">Lihat semua klien</a></div>
                <div class="clients">@foreach ($homeClients as $client)@include('store.partials.client', ['client' => $client])@endforeach</div>
            </div>
        </section>
    @endif

    <section class="block" id="faq">
        <div class="wrap faq">
            <h2>Pertanyaan umum</h2>
            @foreach ($faq as $item)
                <details>
                    <summary>{{ $item['q'] }}</summary>
                    <p>{{ $item['a'] }}</p>
                </details>
            @endforeach
            @if (config('store.whatsapp'))
                <p class="faq__help">Masih ada yang ingin ditanyakan? <a href="https://wa.me/{{ preg_replace('/\D/', '', config('store.whatsapp')) }}" rel="noopener">Chat kami lewat WhatsApp</a>.</p>
            @endif
        </div>
    </section>
@endsection
