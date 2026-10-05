@extends('layouts.store')
@section('content')
@php $wa = preg_replace('/\D/', '', config('store.whatsapp')); @endphp
<div class="wrap page">
    @include('store.partials.crumbs', ['trail' => [['Beranda', route('home')], ['Kontak', route('contact')]]])
    <header class="page__head"><h1>Kontak</h1><p class="page__lead">Tanya stok, cek pesanan, atau datang langsung ke toko.</p></header>

    <div class="split page__split">
        <div>
            <ul class="contact">
                @if ($wa)<li><strong>WhatsApp</strong><a class="btn btn--small" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Halo '.config('store.name').', saya mau tanya...') }}" rel="noopener">Chat {{ config('store.whatsapp') }}</a></li>@endif
                @if (config('store.email'))<li><strong>Email</strong><a href="mailto:{{ config('store.email') }}">{{ config('store.email') }}</a></li>@endif
                @if (config('store.address'))<li><strong>Alamat</strong><span>{{ config('store.address') }}</span>@if (config('store.map_url'))<a href="{{ config('store.map_url') }}" target="_blank" rel="noopener">Buka di Google Maps</a>@endif</li>@endif
                @if (config('store.instagram'))<li><strong>Instagram</strong><a href="https://instagram.com/{{ config('store.instagram') }}" target="_blank" rel="noopener me">{{ '@'.config('store.instagram') }}</a></li>@endif
                @if (config('store.tiktok'))<li><strong>TikTok</strong><a href="https://tiktok.com/{{ '@'.config('store.tiktok') }}" target="_blank" rel="noopener me">{{ '@'.config('store.tiktok') }}</a></li>@endif
                @if (config('store.facebook'))<li><strong>Facebook</strong><a href="{{ config('store.facebook') }}" target="_blank" rel="noopener me">Halaman Facebook</a></li>@endif
            </ul>
            <h2 class="h-sm">Jam buka</h2>
            <table class="hours">@foreach (config('store.hours') as [$day, $time])<tr><th scope="row">{{ $day }}</th><td>{{ $time }}</td></tr>@endforeach</table>
        </div>

        <form class="card-form" method="post" action="{{ route('contact.send') }}">
            @csrf
            <h2 class="h-sm">Kirim pesan</h2>
            @if (session('sent'))<div class="notice-ok" role="status">Pesan terkirim. Kami balas lewat nomor atau email yang kamu isi.</div>@endif
            @if ($errors->any())<div class="alert" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            <div class="hp" aria-hidden="true"><label>Jangan diisi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="field"><label for="c_name">Nama</label><input id="c_name" name="name" value="{{ old('name') }}" required maxlength="80" autocomplete="name"></div>
            <div class="field"><label for="c_phone">Nomor telepon / WhatsApp</label><input id="c_phone" name="phone" value="{{ old('phone') }}" inputmode="tel" maxlength="40" autocomplete="tel"></div>
            <div class="field"><label for="c_email">Email (boleh kosong kalau telepon diisi)</label><input id="c_email" type="email" name="email" value="{{ old('email') }}" maxlength="120" autocomplete="email"></div>
            <div class="field"><label for="c_msg">Pesan</label><textarea id="c_msg" name="message" rows="5" required maxlength="2000">{{ old('message') }}</textarea></div>
            <button class="btn btn--block">Kirim pesan</button>
        </form>
    </div>
</div>
@endsection
