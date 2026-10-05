@extends('layouts.admin')
@section('title', 'Rekonsiliasi')
@section('heading', 'Rekonsiliasi data')
@section('actions')<a class="btn btn--ghost" href="{{ route('admin.reconcile') }}">Periksa ulang</a>@endsection
@section('content')
<div class="flash {{ $result['ok'] ? 'flash--ok' : 'flash--err' }}" role="status">
    {{ $result['ok'] ? 'Semua pemeriksaan lolos. Stok, jurnal, dan pesanan saling cocok.' : 'Ada data yang tidak cocok. Rinciannya di bawah.' }}
    <span class="muted">Diperiksa {{ \Carbon\Carbon::parse($result['checked_at'])->format('d/m/Y H:i:s') }}</span>
</div>
@foreach ($result['checks'] as $c)
<section class="card">
    <h2><span class="badge badge--{{ $c['ok'] ? 'ok' : 'bad' }}">{{ $c['ok'] ? 'Cocok' : 'Selisih' }}</span> {{ $c['title'] }}</h2>
    <p class="muted">{{ $c['summary'] }}</p>
    @if ($c['rows'])
        <div class="scroll"><table>
            <thead><tr>@foreach (array_keys($c['rows'][0]) as $k)<th>{{ str_replace('_', ' ', $k) }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($c['rows'] as $row)<tr>@foreach ($row as $v)<td>{{ is_scalar($v) || $v === null ? $v : json_encode($v) }}</td>@endforeach</tr>@endforeach</tbody>
        </table></div>
    @endif
</section>
@endforeach
@endsection
