@php
    /** @var \App\Models\ProductGroup $product */
    $one = $product->solo();
    $count = $product->sellable()->count();
    $from = $product->hasRange();
@endphp
<article class="card">
    <a class="card__media" href="{{ $product->url() }}" tabindex="-1" aria-hidden="true">
        @include('store.partials.thumb', ['product' => $product, 'size' => 'card'])
        @if ($count > 1)<span class="card__vars">{{ $count }} variasi</span>@endif
    </a>
    <div class="card__body">
        @if ($product->category)<p class="card__cat">{{ $product->category->name }}</p>@endif
        <h3 class="card__name"><a href="{{ $product->url() }}">{{ $product->name }}</a></h3>
        <div class="card__row">
            @if ($from)<span class="card__from">mulai</span>@endif
            <span class="tag">{{ \App\Support\Rupiah::format($product->price_min) }}</span>
            @if ($product->unit())<span class="card__unit">per {{ $product->unit() }}</span>@endif
        </div>
        @include('store.partials.stock', ['product' => $product])
        @if ($one)
            <form method="post" action="{{ route('cart.add', $one->slug) }}" class="card__buy">
                @csrf
                <button class="btn btn--small" type="submit" data-buy="{{ $one->id }}" @disabled(! $one->isInStock())>
                    {{ $one->isInStock() ? 'Tambah' : 'Stok habis' }}
                </button>
            </form>
        @else
            <div class="card__buy"><a class="btn btn--small btn--ghost" href="{{ $product->url() }}">Pilih variasi</a></div>
        @endif
    </div>
</article>
