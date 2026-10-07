@extends('layouts.store')

@php
    use App\Support\Rupiah;
    use App\Support\Qty;

    /** @var \App\Models\ProductGroup $group */
    $variants = $group->sellable();
    $axes = $group->axes();
    $plain = $axes && ($axes[0]['plain'] ?? false);
    $single = $variants->count() === 1;
    $images = $group->images->values();
    $selected = $variants->first(fn ($v) => $v->isInStock()) ?? $variants->first();

    $imageIndex = fn ($variant) => $images->search(fn ($i) => $i->product_id === $variant->id);
    $data = [
        'axes' => collect($axes)->map(fn ($a) => ['name' => $a['name'], 'values' => $a['values']])->all(),
        'selected' => $selected->id,
        'variants' => $variants->map(fn ($v) => [
            'id' => $v->id,
            'slug' => $v->slug,
            'sku' => $v->sku,
            'barcode' => $v->barcode,
            'variant' => $v->variant_name,
            'price' => (int) $v->price,
            'priceLabel' => Rupiah::format($v->price),
            'unit' => $v->unit,
            'options' => $plain ? ['Pilihan' => $v->variant_name] : (object) ($v->options ?? []),
            'image' => $imageIndex($v) === false ? null : $imageIndex($v),
            'cart' => route('cart.add', $v->slug),
        ])->values()->all(),
    ];
@endphp

@section('content')
    <div class="wrap page pdpage">
        @include('store.partials.crumbs', ['trail' => $trail])

        <div class="pdp" data-pdp>
            <div class="pdp__media gal" data-gallery>
                @if ($images->isNotEmpty())
                    <div class="gal__stage">
                        <div class="gal__track" data-track tabindex="0" aria-label="Foto produk, geser untuk melihat foto lain">
                            @foreach ($images as $i => $img)
                                <figure class="gal__slide">
                                    <button type="button" class="gal__zoom" data-zoom="{{ $i }}" aria-label="Perbesar foto {{ $i + 1 }}">
                                        <img src="{{ $img->url('large') }}" alt="{{ $img->alt ?: $group->name }}" width="1000" height="1000"
                                             @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                                    </button>
                                </figure>
                            @endforeach
                        </div>
                        @if ($images->count() > 1)
                            <button type="button" class="gal__nav gal__nav--prev" data-prev aria-label="Foto sebelumnya">‹</button>
                            <button type="button" class="gal__nav gal__nav--next" data-next aria-label="Foto berikutnya">›</button>
                            <span class="gal__count" data-count aria-live="polite">1 / {{ $images->count() }}</span>
                        @endif
                    </div>
                    @if ($images->count() > 1)
                        <ul class="gal__thumbs" role="list">
                            @foreach ($images as $i => $img)
                                <li><button type="button" data-thumb="{{ $i }}" aria-label="Lihat foto {{ $i + 1 }}" @if ($i === 0) aria-current="true" @endif>
                                    <img src="{{ $img->url('thumb') }}" alt="" width="160" height="160" loading="lazy">
                                </button></li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <div class="gal__stage">@include('store.partials.thumb', ['product' => $group, 'size' => 'large', 'eager' => true])</div>
                @endif
            </div>

            <div class="pdp__info">
                <p class="pdp__crumb">
                    @if ($group->category)<a href="{{ $group->category->url() }}">{{ $group->category->name }}</a>@endif
                    @foreach ($group->etalases as $e)<a class="chip chip--sm" href="{{ $e->url() }}">{{ $e->name }}</a>@endforeach
                </p>
                <h1 class="pdp__name">{{ $group->name }}</h1>
                @if ($group->brand)<p class="pdp__brand">Merek <strong>{{ $group->brand }}</strong></p>@endif

                <p class="pdp__price">
                    <span class="tag tag--lg" data-price>{{ Rupiah::format($single || $selected ? $selected->price : $group->price_min) }}</span>
                    <span class="muted">per <span data-unit>{{ $selected->unit }}</span></span>
                </p>
                @if (! $single && $group->hasRange())
                    <p class="muted pdp__range">Kisaran harga {{ Rupiah::format($group->price_min) }} - {{ Rupiah::format($group->price_max) }}</p>
                @endif
                <p>
                    <span class="stock" data-stock-id="{{ $selected->id }}" data-state="{{ $selected->stockState() }}" data-main-stock>
                        <i class="stock__dot" aria-hidden="true"></i><span data-stock-text>{{ $selected->publicStockLabel() }}</span>
                    </span>
                </p>

                @if ($group->summary)<p class="pdp__summary">{{ $group->summary }}</p>@endif

                @if ($errors->has('cart'))<p class="alert" role="alert">{{ $errors->first('cart') }}</p>@endif

                <form method="post" action="{{ route('cart.add', $selected->slug) }}" class="pdp__buy" id="buyform" data-form>
                    @csrf
                    @foreach ($axes as $ai => $axis)
                        <fieldset class="opt" data-axis="{{ $axis['name'] }}">
                            <legend>{{ $plain ? 'Pilih variasi' : $axis['name'] }}: <strong data-axis-value>{{ $plain ? $selected->variant_name : ($selected->options[$axis['name']] ?? '') }}</strong></legend>
                            <div class="opt__list">
                                @foreach ($axis['values'] as $value)
                                    <button type="button" class="opt__chip" data-value="{{ $value }}" aria-pressed="false">{{ $value }}</button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="pdp__qty">
                        <label for="qty">Jumlah</label>
                        <input id="qty" name="qty" type="number" inputmode="decimal" min="0.001" step="any" value="1" required>
                    </div>
                    <div class="pdp__btns">
                        <button class="btn" type="submit" data-buy="{{ $selected->id }}" data-buy-btn @disabled(! $selected->isInStock())>{{ $selected->isInStock() ? 'Tambah ke keranjang' : 'Stok habis' }}</button>
                        <button class="btn btn--ghost" type="submit" name="langsung" value="1" data-buy="{{ $selected->id }}" data-buy-btn @disabled(! $selected->isInStock())>Beli sekarang</button>
                    </div>
                </form>

                @if ($group->highlights)
                    <ul class="ticks">
                        @foreach ($group->highlights as $h)<li>{{ $h }}</li>@endforeach
                    </ul>
                @endif

                <ul class="trust" role="list">
                    <li>@include('store.partials.icon', ['name' => 'stock', 'size' => 20])<span>Stok sama dengan di toko</span></li>
                    <li>@include('store.partials.icon', ['name' => 'pickup', 'size' => 20])<span>Ambil di toko gratis</span></li>
                    <li>@include('store.partials.icon', ['name' => 'safe', 'size' => 20])<span>Harga jelas, tanpa biaya tersembunyi</span></li>
                </ul>
            </div>
        </div>

        <div class="pdp__more">
            <section class="pdp__sec" aria-labelledby="desc-h">
                <h2 id="desc-h">Tentang produk ini</h2>
                @if ($group->description)
                    <div class="prose">
                        @foreach (preg_split('/\R{2,}/', trim($group->description)) as $para)
                            <p>{!! nl2br(e($para)) !!}</p>
                        @endforeach
                    </div>
                @else
                    <p class="muted">Belum ada deskripsi untuk produk ini.</p>
                @endif
            </section>

            <section class="pdp__sec" aria-labelledby="spec-h">
                <h2 id="spec-h">Spesifikasi</h2>
                <dl class="specs">
                    @if ($group->category)<div><dt>Kategori</dt><dd><a href="{{ $group->category->url() }}">{{ $group->category->name }}</a></dd></div>@endif
                    @if ($group->brand)<div><dt>Merek</dt><dd>{{ $group->brand }}</dd></div>@endif
                    @foreach ((array) $group->specs as $spec)
                        @if (! empty($spec['label']) && ! empty($spec['value']))<div><dt>{{ $spec['label'] }}</dt><dd>{{ $spec['value'] }}</dd></div>@endif
                    @endforeach
                    <div><dt>Kode (SKU)</dt><dd data-sku>{{ $selected->sku }}</dd></div>
                    <div><dt>Satuan</dt><dd data-unit>{{ $selected->unit }}</dd></div>
                    @if ($selected->barcode)<div data-barcode-row><dt>Barcode</dt><dd data-barcode>{{ $selected->barcode }}</dd></div>@else<div data-barcode-row hidden><dt>Barcode</dt><dd data-barcode></dd></div>@endif
                </dl>
            </section>

            @unless ($single)
                <section class="pdp__sec pdp__sec--wide" aria-labelledby="var-h">
                    <h2 id="var-h">Semua variasi dan harga</h2>
                    <div class="scrollx">
                        <table class="vtable">
                            <thead><tr><th>Variasi</th><th>Kode (SKU)</th><th class="num">Harga</th><th>Stok</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($variants as $v)
                                <tr data-row="{{ $v->id }}" @class(['is-on' => $v->id === $selected->id])>
                                    <td><strong>{{ $v->variant_name ?: $v->name }}</strong>
                                        @if ($v->options && ! $plain)<div class="muted">{{ collect($v->options)->implode(' / ') }}</div>@endif</td>
                                    <td>{{ $v->sku }}</td>
                                    <td class="num">{{ Rupiah::format($v->price) }} <small class="muted">/ {{ $v->unit }}</small></td>
                                    <td>@include('store.partials.stock', ['product' => $v])</td>
                                    <td class="num"><button type="button" class="btn btn--small btn--ghost" data-pick="{{ $v->id }}">Pilih</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endunless
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

    <div class="buybar" data-buybar>
        <div class="buybar__price"><strong data-price>{{ Rupiah::format($selected->price) }}</strong><small data-buybar-name>{{ $selected->variant_name ?: $group->name }}</small></div>
        <button class="btn" type="submit" form="buyform" data-buy="{{ $selected->id }}" data-buy-btn @disabled(! $selected->isInStock())>{{ $selected->isInStock() ? 'Tambah ke keranjang' : 'Stok habis' }}</button>
    </div>

    <dialog class="lightbox" data-lightbox aria-label="Foto produk diperbesar">
        <button type="button" class="lightbox__close" data-close aria-label="Tutup">×</button>
        <img src="" alt="">
    </dialog>

    <script type="application/json" id="pdp-data">@json($data)</script>
@endsection

@push('scripts')
    <script src="{{ asset('js/pdp.js') }}?v={{ @filemtime(public_path('js/pdp.js')) }}" defer></script>
@endpush
