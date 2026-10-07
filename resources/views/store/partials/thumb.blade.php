@php
    $size = $size ?? 'card';
    $img = $product instanceof \App\Models\ProductGroup ? $product->imageUrl($size) : $product->imageUrl();
    $px = $size === 'card' ? 640 : 1400;
@endphp
@if ($img)
    <img class="thumb thumb--img" src="{{ $img }}" alt="{{ $product->name }}" loading="{{ $eager ?? false ? 'eager' : 'lazy' }}" width="{{ $px }}" height="{{ $px }}" decoding="async">
@else
    <div class="thumb" style="--h:{{ $product->hue() }}" role="img" aria-label="{{ $product->name }}"><span>{{ $product->initials() }}</span></div>
@endif
