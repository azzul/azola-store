@extends('layouts.store')

@section('content')
    <div class="wrap page">
        @include('store.partials.crumbs', ['trail' => $trail])
        <h1 class="page__title">Artikel</h1>
        <p class="page__lead">Tips belanja, info produk, dan kabar dari toko.</p>

        @if ($topics->isNotEmpty())
            <nav class="chips" aria-label="Topik artikel">
                <a class="chip {{ $topic === '' ? 'is-on' : '' }}" href="{{ route('articles.index') }}">Semua</a>
                @foreach ($topics as $t)
                    <a class="chip {{ $topic === $t ? 'is-on' : '' }}" href="{{ route('articles.index', ['topik' => $t]) }}">{{ $t }}</a>
                @endforeach
            </nav>
        @endif

        @if ($articles->isEmpty())
            <div class="empty" style="margin-top:1.5rem">
                <p><strong>Belum ada artikel.</strong></p>
                <p>Tulisan baru akan muncul di sini. Sementara itu, <a href="{{ route('shop.index') }}">lihat produk kami</a>.</p>
            </div>
        @else
            <div class="agrid">
                @foreach ($articles as $article)
                    @include('store.articles.card', ['article' => $article, 'big' => $loop->first && $articles->currentPage() === 1 && $topic === ''])
                @endforeach
            </div>
            {{ $articles->links('pagination.store') }}
        @endif
    </div>
@endsection
