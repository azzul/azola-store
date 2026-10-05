@if ($product->imageUrl())
    <img class="thumb thumb--img" src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="{{ $eager ?? false ? 'eager' : 'lazy' }}" width="480" height="480" decoding="async">
@else
    <div class="thumb" style="--h:{{ $product->hue() }}" role="img" aria-label="{{ $product->name }}"><span>{{ $product->initials() }}</span></div>
@endif
