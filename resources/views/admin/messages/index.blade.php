@extends('layouts.admin')
@section('title', 'Pesan masuk')
@section('heading', 'Pesan masuk')
@section('content')
<section class="card scroll">
    <table>
        <thead><tr><th>Dari</th><th>Pesan</th><th>Waktu</th></tr></thead>
        <tbody>
        @forelse ($messages as $m)
            <tr>
                <td>{!! $m->read_at ? '' : '<span class="badge badge--warn">baru</span> ' !!}<a href="{{ route('admin.messages.show', $m) }}">{{ $m->name }}</a><div class="muted">{{ $m->phone ?: $m->email }}</div></td>
                <td>{{ \Illuminate\Support\Str::limit($m->message, 110) }}</td>
                <td>{{ $m->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Belum ada pesan dari halaman Kontak.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $messages->links('pagination.store') }}
</section>
@endsection
