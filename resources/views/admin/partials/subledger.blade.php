{{-- Buku pembantu dari jurnal. Variabel: $card, $plus (judul kolom bertambah), $minus (berkurang), $side ('credit' atau 'debit' = kolom yang menambah saldo) --}}
@php($side = $side ?? 'credit')
<div class="scroll">
<table>
    <thead><tr><th>Tanggal</th><th>Nomor</th><th>Keterangan</th><th class="num">{{ $plus }}</th><th class="num">{{ $minus }}</th><th class="num">Saldo</th></tr></thead>
    <tbody>
        <tr class="sub"><td colspan="5">Saldo awal</td><td class="num">@rp($card['opening'])</td></tr>
        @forelse ($card['rows'] as $r)
            @php($l = $r['line'])
            @php($inc = $side === 'credit' ? $l->credit : $l->debit)
            @php($dec = $side === 'credit' ? $l->debit : $l->credit)
            <tr>
                <td>{{ \Carbon\Carbon::parse($l->date)->format('d/m/Y') }}</td>
                <td><a href="{{ route('admin.journals.show', $l->journal_id) }}">{{ $l->number }}</a></td>
                <td>{{ $l->description }}</td>
                <td class="num">{{ $inc ? \App\Support\Rupiah::format($inc) : '' }}</td>
                <td class="num">{{ $dec ? \App\Support\Rupiah::format($dec) : '' }}</td>
                <td class="num">@rp($r['balance'])</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Tidak ada transaksi pada periode ini.</td></tr>
        @endforelse
    </tbody>
    <tfoot><tr><td colspan="5">Saldo akhir</td><td class="num">@rp($card['closing'])</td></tr></tfoot>
</table>
</div>
