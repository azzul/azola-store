@extends('layouts.store')
@section('content')
<div class="wrap page">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Klien dan mitra', route('clients')]]])
    <header class="page__head"><h1>Klien dan mitra</h1><p class="page__lead">Yang berbelanja dan bekerja sama dengan {{ config('store.name') }}.</p></header>
    @if ($clients->isEmpty())
        <p class="muted">Daftar klien belum ditampilkan.</p>
    @else
        <div class="clients clients--lg">@foreach ($clients as $client)@include('store.partials.client', ['client' => $client])@endforeach</div>
    @endif
</div>
@endsection
