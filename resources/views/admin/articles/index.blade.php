@extends('layouts.admin')
@section('title', 'Artikel')
@section('heading', 'Artikel')
@section('actions')<a class="btn" href="{{ route('admin.articles.create') }}">Tulis artikel</a>@endsection
@section('content')
<section class="card scroll">
<table>
    <thead><tr><th></th><th>Judul</th><th>Topik</th><th>Terbit</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($articles as $a)
        <tr>
            <td>@if ($a->coverUrl('card'))<img class="thumb" src="{{ $a->coverUrl('card') }}" alt="" loading="lazy">@else<span class="thumb">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($a->title, 0, 1)) }}</span>@endif</td>
            <td><a href="{{ route('admin.articles.edit', $a) }}">{{ $a->title }}</a><div class="muted">{{ $a->readingMinutes() }} menit baca</div></td>
            <td>{{ $a->topic ?: '-' }}</td>
            <td>{{ $a->published_at?->format('d/m/Y H:i') }}</td>
            <td>@if (! $a->is_published)<span class="badge">Draf</span>@elseif ($a->published_at->isFuture())<span class="badge badge--warn">Terjadwal</span>@else<span class="badge badge--ok">Terbit</span>@endif</td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">Belum ada artikel. <a href="{{ route('admin.articles.create') }}">Tulis yang pertama</a>.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $articles->links('pagination.store') }}
</section>
@endsection
