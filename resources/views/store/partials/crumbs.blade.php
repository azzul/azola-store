<nav class="crumbs" aria-label="Jejak halaman">
    <ol>
        @foreach ($trail as $i => $item)
            @if ($loop->last)
                <li aria-current="page">{{ $item[0] }}</li>
            @else
                <li><a href="{{ $item[1] }}">{{ $item[0] }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
