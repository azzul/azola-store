@extends('layouts.admin')
@section('title', 'Pesan')
@section('heading', 'Pesan dari '.$message->name)
@section('actions')<a class="btn btn--ghost" href="{{ route('admin.messages.index') }}">Kembali</a>@endsection
@section('content')
@php $wa = preg_replace('/\D/', '', (string) $message->phone); if (str_starts_with($wa, '0')) { $wa = '62'.substr($wa, 1); } @endphp
<section class="card">
    <dl class="kv">
        <dt>Waktu</dt><dd>{{ $message->created_at?->format('d/m/Y H:i') }}</dd>
        @if ($message->phone)<dt>Telepon</dt><dd>{{ $message->phone }} <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener">Balas lewat WhatsApp</a></dd>@endif
        @if ($message->email)<dt>Email</dt><dd><a href="mailto:{{ $message->email }}">{{ $message->email }}</a></dd>@endif
    </dl>
    <p style="margin-top:1rem; white-space:pre-line">{{ $message->message }}</p>
    <form method="post" action="{{ route('admin.messages.destroy', $message) }}" onsubmit="return confirm('Hapus pesan ini?')">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">Hapus</button></form>
</section>
@endsection
