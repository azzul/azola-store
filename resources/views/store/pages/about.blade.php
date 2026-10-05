@extends('layouts.store')
@section('content')
<div class="wrap page">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Tentang kami', route('about')]]])
    <header class="page__head">
        <h1>{{ config('store.about.headline') }}</h1>
        <p class="page__lead">Tentang {{ config('store.name') }}</p>
    </header>

    <div class="split page__split">
        <div class="prose">
            @foreach (config('store.about.story') as $para)<p>{{ $para }}</p>@endforeach
        </div>
        <dl class="facts">
            @if (config('store.about.since'))<div><dt>Berdiri</dt><dd>{{ config('store.about.since') }}</dd></div>@endif
            <div><dt>Produk online</dt><dd>{{ $products }}</dd></div>
            <div><dt>Kategori</dt><dd>{{ $categories }}</dd></div>
            @if ($reviews['count'])<div><dt>Nilai pelanggan</dt><dd>{{ str_replace('.', ',', $reviews['average']) }} dari 5 <small>({{ $reviews['count'] }} ulasan)</small></dd></div>@endif
        </dl>
    </div>
</div>

<section class="block block--tint">
    <div class="wrap">
        <h2>Yang kami pegang</h2>
        <ul class="values">
            @foreach (config('store.about.values') as [$title, $text])
                <li><h3>{{ $title }}</h3><p>{{ $text }}</p></li>
            @endforeach
        </ul>
    </div>
</section>

@if ($clients->isNotEmpty())
<section class="block">
    <div class="wrap">
        <div class="block__head"><h2>Dipercaya oleh</h2><a href="{{ route('clients') }}">Lihat semua klien</a></div>
        <div class="clients">@foreach ($clients as $client)@include('store.partials.client', ['client' => $client])@endforeach</div>
    </div>
</section>
@endif

<section class="block block--cta">
    <div class="wrap cta">
        <h2>Ada yang ingin ditanyakan?</h2>
        <p>Kami balas di jam buka toko.</p>
        <a class="btn" href="{{ route('contact') }}">Hubungi kami</a>
    </div>
</section>
@endsection
