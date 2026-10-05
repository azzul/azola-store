<figure class="review">
    @include('store.partials.stars', ['rating' => $review->rating])
    <blockquote><p>{{ $review->body }}</p></blockquote>
    <figcaption><strong>{{ $review->name }}</strong>@if ($review->role)<span class="muted">, {{ $review->role }}</span>@endif</figcaption>
</figure>
