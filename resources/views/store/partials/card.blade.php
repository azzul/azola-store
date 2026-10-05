<article class="card">
    <a class="card__media" href="{{ $product->url() }}" tabindex="-1" aria-hidden="true">
        @include('store.partials.thumb', ['product' => $product])
    </a>
    <div class="card__body">
        @if ($product->category)<p class="card__cat">{{ $product->category->name }}</p>@endif
        <h3 class="card__name"><a href="{{ $product->url() }}">{{ $product->name }}</a></h3>
        <div class="card__row">
            <span class="tag">{{ \App\Support\Rupiah::format($product->price) }}</span>
            <span class="card__unit">per {{ $product->unit }}</span>
        </div>
        @include('store.partials.stock', ['product' => $product])
        <form method="post" action="{{ route('cart.add', $product->slug) }}" class="card__buy">
            @csrf
            <button class="btn btn--small" type="submit" data-buy="{{ $product->id }}" @disabled(! $product->isInStock())>
                {{ $product->isInStock() ? 'Tambah' : 'Stok habis' }}
            </button>
        </form>
    </div>
</article>
