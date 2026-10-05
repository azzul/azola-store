@extends('layouts.store')

@section('content')
    <div class="wrap page">
        @include('store.partials.crumbs', ['trail' => $trail])

        <div class="pdp">
            <div class="pdp__media">
                @include('store.partials.thumb', ['product' => $product, 'eager' => true])
            </div>

            <div class="pdp__info">
                <h1 class="pdp__name">{{ $product->name }}</h1>
                <p class="pdp__price"><span class="tag tag--lg">{{ \App\Support\Rupiah::format($product->price) }}</span> <span class="muted">per {{ $product->unit }}</span></p>
                <p>@include('store.partials.stock', ['product' => $product])</p>

                @if ($errors->has('cart'))<p class="alert" role="alert">{{ $errors->first('cart') }}</p>@endif

                <form method="post" action="{{ route('cart.add', $product->slug) }}" class="pdp__buy">
                    @csrf
                    <div>
                        <label for="qty">Jumlah</label>
                        <input id="qty" name="qty" type="number" inputmode="decimal" min="0.001" step="any" value="1" required>
                    </div>
                    <button class="btn" type="submit" data-buy="{{ $product->id }}" @disabled(! $product->isInStock())>{{ $product->isInStock() ? 'Tambah ke keranjang' : 'Stok habis' }}</button>
                    <button class="btn btn--ghost" type="submit" name="langsung" value="1" @disabled(! $product->isInStock())>Beli sekarang</button>
                </form>

                @if ($product->description)
                    <div class="prose">
                        <h2>Tentang produk ini</h2>
                        <p>{!! nl2br(e($product->description)) !!}</p>
                    </div>
                @endif

                <dl class="facts">
                    <div><dt>Kode</dt><dd>{{ $product->sku }}</dd></div>
                    @if ($product->category)<div><dt>Kategori</dt><dd><a href="{{ $product->category->url() }}">{{ $product->category->name }}</a></dd></div>@endif
                    <div><dt>Satuan</dt><dd>{{ $product->unit }}</dd></div>
                </dl>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="block block--flush">
                <h2>Produk lain yang mungkin kamu cari</h2>
                <div class="grid">
                    @foreach ($related as $item)
                        @include('store.partials.card', ['product' => $item])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
