@php $tag = $client->url ? 'a' : 'div'; @endphp
<{{ $tag }} class="client" @if ($client->url) href="{{ $client->url }}" target="_blank" rel="nofollow noopener" @endif>
    @if ($client->logoUrl())
        <img src="{{ $client->logoUrl() }}" alt="{{ $client->name }}" loading="lazy" width="160" height="80">
    @else
        <span class="client__mono" aria-hidden="true">{{ $client->initials() }}</span>
        <span class="client__name">{{ $client->name }}</span>
    @endif
    @if ($client->note)<small>{{ $client->note }}</small>@endif
</{{ $tag }}>
