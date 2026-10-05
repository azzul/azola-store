@if ($paginator->hasPages())
    <nav class="pager" aria-label="Halaman">
        @if ($paginator->onFirstPage())
            <span class="pager__item is-off">Sebelumnya</span>
        @else
            <a class="pager__item" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
        @endif

        <span class="pager__info">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="pager__item" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
        @else
            <span class="pager__item is-off">Berikutnya</span>
        @endif
    </nav>
@endif
