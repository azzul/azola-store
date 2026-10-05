{{-- Label stok yang ikut berubah realtime lewat public/js/live-stock.js --}}
<span class="stock" data-stock-id="{{ $product->id }}" data-state="{{ $product->stockState() }}">
    <i class="stock__dot" aria-hidden="true"></i><span data-stock-text>{{ $product->publicStockLabel() }}</span>
</span>
