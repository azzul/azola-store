@extends('layouts.store')
@section('content')
<div class="wrap page">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Pertanyaan umum', route('faq')]]])
    <header class="page__head"><h1>Pertanyaan umum</h1></header>
    <div class="faq">
        @foreach ($faq as $item)<details><summary>{{ $item['q'] }}</summary><p>{{ $item['a'] }}</p></details>@endforeach
        <p class="faq__help">Belum terjawab? <a href="{{ route('contact') }}">Hubungi kami</a>.</p>
    </div>
</div>
@endsection
