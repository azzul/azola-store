@extends('layouts.store')

@section('content')
    <div class="wrap page">
        @include('store.partials.crumbs', ['trail' => $trail])

        <article class="post">
            <header class="post__head">
                <p class="acard__meta">
                    @if ($article->topic)<a class="chip chip--sm" href="{{ route('articles.index', ['topik' => $article->topic]) }}">{{ $article->topic }}</a>@endif
                    <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->translatedFormat('j F Y') }}</time>
                    <span>{{ $article->readingMinutes() }} menit baca</span>
                </p>
                <h1 class="post__title">{{ $article->title }}</h1>
                @if ($article->excerpt)<p class="post__lead">{{ $article->excerpt }}</p>@endif
                <p class="post__by">Oleh <strong>{{ $article->author ?: config('store.name') }}</strong></p>
            </header>

            @if ($article->coverUrl())
                <figure class="post__cover"><img src="{{ $article->coverUrl() }}" alt="{{ $article->cover_alt ?: $article->title }}" width="1400" height="788" fetchpriority="high"></figure>
            @endif

            <div class="post__body prose">{!! $article->html() !!}</div>

            <footer class="post__foot">
                <a class="btn btn--ghost" href="https://wa.me/?text={{ rawurlencode($article->title.' '.$article->url()) }}" target="_blank" rel="noopener">Bagikan lewat WhatsApp</a>
                <a class="btn" href="{{ route('shop.index') }}">Lihat produk</a>
            </footer>
        </article>

        @if ($related->isNotEmpty())
            <section class="block block--flush">
                <h2>Artikel lainnya</h2>
                <div class="agrid agrid--3">
                    @foreach ($related as $item)@include('store.articles.card', ['article' => $item])@endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
