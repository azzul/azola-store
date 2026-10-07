@extends('layouts.admin')
@section('title', 'Supplier')
@section('heading', 'Supplier')
@section('actions')<a class="btn" href="{{ route('admin.suppliers.create') }}">Tambah supplier</a>@endsection
@section('content')
<form class="filters" method="get">
    <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama, telepon, atau kode"></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="">Aktif & nonaktif</option><option value="off" @selected(request('status') === 'off')>Nonaktif saja</option></select></div>
    <button class="btn btn--ghost">Terapkan</button>
</form>
<section class="card scroll">
    <table>
        <thead><tr><th>Supplier</th><th>Telepon</th><th class="num">Tempo</th><th class="num">Hutang</th><th class="num">Piutang supplier</th></tr></thead>
        <tbody>
        @forelse ($suppliers as $s)
            <tr>
                <td><a href="{{ route('admin.suppliers.show', $s) }}">{{ $s->name }}</a>@unless ($s->is_active) <span class="badge badge--bad">nonaktif</span>@endunless<div class="muted">{{ $s->address ? \Illuminate\Support\Str::limit($s->address, 60) : '' }}</div></td>
                <td>{{ $s->phone ?: '-' }}</td>
                <td class="num">{{ $s->term_days }} hari</td>
                <td class="num">@rp($s->payable())</td>
                <td class="num">@rp($s->receivable())</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada supplier. <a href="{{ route('admin.suppliers.create') }}">Tambah supplier pertama</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $suppliers->links('pagination.store') }}
</section>
@endsection
