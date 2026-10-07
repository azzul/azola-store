{{-- Label stok yang ikut berubah realtime lewat public/js/live-stock.js.
     Produk satu variasi mengikuti stok variasinya; produk banyak variasi dihitung dari semua variasinya di browser. --}}
@if ($product instanceof \App\Models\ProductGroup && ! $product->isSingle())
    <span class="stock" data-stock-group="{{ $product->id }}" data-state="{{ $product->stockState() }}"
          data-members="{{ $product->sellable()->map(fn ($v) => $v->id.':'.$v->stockState())->implode(',') }}">
        <i class="stock__dot" aria-hidden="true"></i><span data-stock-text>{{ $product->publicStockLabel() }}</span>
    </span>
@else
    @php $sid = $product instanceof \App\Models\ProductGroup ? $product->solo()->id : $product->id; @endphp
    <span class="stock" data-stock-id="{{ $sid }}" data-state="{{ $product->stockState() }}">
        <i class="stock__dot" aria-hidden="true"></i><span data-stock-text>{{ $product->publicStockLabel() }}</span>
    </span>
@endif
