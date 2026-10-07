@extends('layouts.admin')
@section('title', $supplier->name)
@section('heading', $supplier->name)
@section('actions')
    <a class="btn" href="{{ route('admin.purchases.create', ['supplier' => $supplier->id]) }}">Input pembelian</a>
    <a class="btn btn--ghost" href="{{ route('admin.supplier-payments.create', ['supplier' => $supplier->id]) }}">Bayar hutang</a>
    <a class="btn btn--ghost" href="{{ route('admin.suppliers.edit', $supplier) }}">Ubah</a>
@endsection
@section('content')
<div class="kpis">
    <div class="kpi {{ $supplier->payable() > 0 ? 'kpi--warn' : '' }}"><b>@rp($supplier->payable())</b><span>Hutang ke supplier</span></div>
    <div class="kpi"><b>@rp($supplier->receivable())</b><span>Piutang supplier</span></div>
    <div class="kpi"><b>{{ $supplier->term_days }} hari</b><span>Tempo bawaan</span></div>
</div>
<div class="grid grid--2">
    <section class="card"><h2>Data</h2>
        <dl class="kv"><dt>Kontak</dt><dd>{{ $supplier->contact ?: '-' }}</dd><dt>Telepon</dt><dd>{{ $supplier->phone ?: '-' }}</dd><dt>Email</dt><dd>{{ $supplier->email ?: '-' }}</dd><dt>Alamat</dt><dd>{{ $supplier->address ?: '-' }}</dd><dt>Catatan</dt><dd>{{ $supplier->note ?: '-' }}</dd></dl>
        <p class="actions no-print"><a href="{{ route('admin.cards.payable', ['supplier' => $supplier->id]) }}">Kartu hutang</a><a href="{{ route('admin.supplier-receivables.index', ['supplier' => $supplier->id]) }}">Piutang supplier</a></p>
    </section>
    <section class="card scroll"><h2>Faktur belum lunas</h2>
        <table><thead><tr><th>Faktur</th><th>Jatuh tempo</th><th class="num">Sisa</th></tr></thead><tbody>
        @forelse ($open as $p)<tr><td><a href="{{ route('admin.purchases.show', $p) }}">{{ $p->number }}</a></td><td>{{ $p->due_date?->format('d/m/Y') ?: '-' }} @if ($p->isOverdue())<span class="badge badge--bad">lewat</span>@endif</td><td class="num">@rp($p->outstanding())</td></tr>
        @empty<tr><td colspan="3" class="muted">Tidak ada hutang.</td></tr>@endforelse
        </tbody></table>
    </section>
</div>
<div class="grid grid--3">
    <section class="card scroll"><h2>Pembelian terakhir</h2><table><tbody>
        @forelse ($recent as $p)<tr><td><a href="{{ route('admin.purchases.show', $p) }}">{{ $p->number }}</a><div class="muted">{{ $p->date->format('d/m/Y') }}</div></td><td class="num">@rp($p->grand_total)</td></tr>@empty<tr><td class="muted">Belum ada.</td></tr>@endforelse
    </tbody></table></section>
    <section class="card scroll"><h2>Pembayaran terakhir</h2><table><tbody>
        @forelse ($payments as $p)<tr><td>{{ $p->number }}<div class="muted">{{ $p->date->format('d/m/Y') }} · {{ $p->direction === 'pay' ? 'bayar hutang' : 'terima piutang' }}</div></td><td class="num">@rp($p->amount)</td></tr>@empty<tr><td class="muted">Belum ada.</td></tr>@endforelse
    </tbody></table></section>
    <section class="card scroll"><h2>Retur terakhir</h2><table><tbody>
        @forelse ($returns as $r)<tr><td><a href="{{ route('admin.purchase-returns.show', $r) }}">{{ $r->number }}</a><div class="muted">{{ $r->date->format('d/m/Y') }}</div></td><td class="num">@rp($r->total)</td></tr>@empty<tr><td class="muted">Belum ada.</td></tr>@endforelse
    </tbody></table></section>
</div>
@endsection
