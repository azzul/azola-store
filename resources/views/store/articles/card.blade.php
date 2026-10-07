<article class="acard {{ ($big ?? false) ? 'acard--big' : '' }}">
    <a class="acard__media" href="{{ $article->url() }}" tabindex="-1" aria-hidden="true">
        @if ($article->coverUrl('card'))
            <img src="{{ $article->coverUrl('card') }}" alt="" loading="{{ ($big ?? false) ? 'eager' : 'lazy' }}" width="1000" height="560" decoding="async">
        @else
            <span class="acard__ph" style="--h:{{ $article->hue() }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($article->title, 0, 1)) }}</span>
        @endif
    </a>
    <div class="acard__body">
        <p class="acard__meta">
            @if ($article->topic)<span class="chip chip--sm">{{ $article->topic }}</span>@endif
            <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->translatedFormat('j F Y') }}</time>
            <span>{{ $article->readingMinutes() }} menit baca</span>
        </p>
        <h2 class="acard__title"><a href="{{ $article->url() }}">{{ $article->title }}</a></h2>
        <p class="acard__sum">{{ $article->summary() }}</p>
    </div>
</article>
