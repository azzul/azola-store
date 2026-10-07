@extends('layouts.store')

@section('content')
    <div class="wrap page">
        @include('store.partials.crumbs', ['trail' => $trail])
        <div class="plhead">
            <div>
                <h1 class="page__title">Pricelist</h1>
                <p class="page__lead">Daftar harga lengkap per variasi dan SKU, dengan status stok terbaru. Cocok untuk pesanan dalam jumlah banyak.</p>
            </div>
            <a class="btn plhead__dl" href="{{ route('pricelist.pdf', $query) }}" download>
                @include('store.partials.icon', ['name' => 'download', 'size' => 18]) Unduh PDF
            </a>
        </div>

        <form class="filters filters--pl" action="{{ route('pricelist') }}" method="get">
            <div class="filters__q"><label for="q" class="sr">Cari</label><input id="q" name="q" type="search" value="{{ $q }}" placeholder="Cari nama atau SKU"></div>
            <div>
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori">
                    <option value="">Semua</option>
                    @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected($kategori === $c->slug)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <label class="check filters__stock"><input type="checkbox" name="tersedia" value="1" @checked($onlyStock)> Hanya yang ada stok</label>
            <button class="btn btn--ghost" type="submit">Terapkan</button>
        </form>

        @if ($sections->isEmpty())
            <div class="empty"><p><strong>Tidak ada produk yang cocok.</strong></p><p><a href="{{ route('pricelist') }}">Tampilkan semua</a></p></div>
        @else
            <p class="count">{{ $total }} SKU dalam {{ $sections->count() }} kategori. Unduhan PDF mengikuti penyaringan di atas.</p>
            @foreach ($sections as $section)
                <section class="plsec" aria-labelledby="pl-{{ $loop->index }}">
                    <h2 id="pl-{{ $loop->index }}" class="plsec__h">{{ $section['name'] }} <span>{{ $section['rows']->count() }} SKU</span></h2>
                    <div class="scrollx">
                        <table class="pltable">
                            <thead><tr><th>Produk</th><th>SKU</th><th>Satuan</th><th class="num">Harga</th><th>Stok</th></tr></thead>
                            <tbody>
                            @foreach ($section['rows'] as $row)
                                <tr>
                                    <td data-label="Produk"><a href="{{ $row['url'] }}"><strong>{{ $row['product'] }}</strong>@if ($row['variant']) <span class="muted">{{ $row['variant'] }}</span>@endif</a></td>
                                    <td data-label="SKU">{{ $row['sku'] }}</td>
                                    <td data-label="Satuan">{{ $row['unit'] }}</td>
                                    <td data-label="Harga" class="num"><strong>{{ \App\Support\Rupiah::format($row['price']) }}</strong></td>
                                    <td data-label="Stok"><span class="stock" data-state="{{ $row['state'] }}"><i class="stock__dot" aria-hidden="true"></i>{{ $row['stock'] }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
            <p class="muted pl__note">Harga dan stok dapat berubah. Status stok adalah kondisi saat halaman ini dibuka.</p>
        @endif
    </div>
@endsection
