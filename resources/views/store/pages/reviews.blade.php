@extends('layouts.store')
@section('content')
<div class="wrap page">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Ulasan pelanggan', route('reviews')]]])
    <header class="page__head"><h1>Ulasan pelanggan</h1><p class="page__lead">Ditulis sendiri oleh pembeli. Kami hanya memeriksa sebelum tampil, tidak menyunting isinya.</p></header>

    <div class="split page__split">
        <div>
            @if ($summary['count'])
                <div class="rating">
                    <div class="rating__big">{{ str_replace('.', ',', $summary['average']) }}<small>dari 5</small></div>
                    <div>@include('store.partials.stars', ['rating' => (int) round($summary['average'])])<div class="muted">{{ $summary['count'] }} ulasan</div></div>
                </div>
                <ul class="bars">
                    @foreach ($summary['bars'] as $star => $n)
                        <li><span>{{ $star }} bintang</span><i style="--w: {{ round($n / $summary['count'] * 100) }}%"></i><span>{{ $n }}</span></li>
                    @endforeach
                </ul>
                <div class="reviews">@foreach ($reviews as $review)@include('store.partials.review', ['review' => $review])@endforeach</div>
                {{ $reviews->links('pagination.store') }}
            @else
                <p>Belum ada ulasan. Jadilah yang pertama menulis.</p>
            @endif
        </div>

        <form id="tulis" class="card-form" method="post" action="{{ route('reviews.store') }}">
            @csrf
            <h2 class="h-sm">Tulis ulasan</h2>
            @if (session('sent'))<div class="notice-ok" role="status">Terima kasih. Ulasanmu akan tampil setelah kami periksa.</div>@endif
            @if ($errors->any())<div class="alert" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            <div class="hp" aria-hidden="true"><label>Jangan diisi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <fieldset class="rate">
                <legend>Penilaian</legend>
                <div class="rate__stars">
                @for ($i = 5; $i >= 1; $i--)
                    <input type="radio" id="r{{ $i }}" name="rating" value="{{ $i }}" @checked((int) old('rating') === $i) required><label for="r{{ $i }}" title="{{ $i }} bintang"><span class="sr">{{ $i }} bintang</span>&#9733;</label>
                @endfor
                </div>
            </fieldset>
            <div class="field"><label for="v_name">Nama (boleh nama depan saja)</label><input id="v_name" name="name" value="{{ old('name') }}" required maxlength="80"></div>
            <div class="field"><label for="v_role">Keterangan (opsional)</label><input id="v_role" name="role" value="{{ old('role') }}" maxlength="80" placeholder="Mis. pelanggan sejak 2022"></div>
            <div class="field"><label for="v_body">Ulasan</label><textarea id="v_body" name="body" rows="4" required minlength="10" maxlength="600">{{ old('body') }}</textarea></div>
            <button class="btn btn--block">Kirim ulasan</button>
        </form>
    </div>
</div>
@endsection
