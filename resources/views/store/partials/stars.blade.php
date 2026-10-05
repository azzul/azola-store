<span class="stars" role="img" aria-label="{{ $rating }} dari 5 bintang">@for ($i = 1; $i <= 5; $i++)<span class="{{ $i <= $rating ? 'on' : '' }}" aria-hidden="true">&#9733;</span>@endfor</span>
