@extends('layouts.store')

@section('content')
    <div class="wrap page">
        @include('store.partials.crumbs', ['trail' => $trail])
        <h1 class="page__title">{{ $heading }}</h1>
        @if ($category && $category->description)<p class="page__lead">{{ $category->description }}</p>@endif

        <form class="filters" action="{{ $category ? $category->url() : route('shop.index') }}" method="get">
            <div class="filters__q">
                <label for="q" class="sr">Cari produk</label>
                <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Cari nama barang atau kode">
            </div>
            @unless ($category)
                <div>
                    <label for="kategori">Kategori</label>
                    <select id="kategori" name="kategori">
                        <option value="">Semua</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->slug }}" @selected(request('kategori') === $c->slug)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div>
                <label for="urut">Urutkan</label>
                <select id="urut" name="urut">
                    @foreach ($sorts as $key => $label)
                        <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn--ghost" type="submit">Terapkan</button>
        </form>

        @if ($products->isEmpty())
            <div class="empty">
                <p><strong>Tidak ada produk yang cocok.</strong></p>
                <p>Coba kata kunci yang lebih pendek, atau <a href="{{ route('shop.index') }}">lihat semua produk</a>.</p>
            </div>
        @else
            <p class="count">{{ $products->total() }} produk</p>
            <div class="grid">
                @foreach ($products as $product)
                    @include('store.partials.card', ['product' => $product])
                @endforeach
            </div>
            {{ $products->links('pagination.store') }}
        @endif
    </div>
@endsection
