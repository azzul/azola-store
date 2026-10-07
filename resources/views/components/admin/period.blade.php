@props(['from', 'to', 'single' => false, 'label' => 'Tampilkan', 'csv' => false])
{{-- Filter periode: dua tanggal + tombol cepat. Slot = filter tambahan. --}}
<form class="filters" method="get">
    @if ($single)
        <div><label for="to">Tanggal</label><input id="to" type="date" name="to" value="{{ $to }}"></div>
    @else
        <div><label for="from">Dari</label><input id="from" type="date" name="from" value="{{ $from }}"></div>
        <div><label for="to">Sampai</label><input id="to" type="date" name="to" value="{{ $to }}"></div>
    @endif
    {{ $slot }}
    <button class="btn btn--ghost">{{ $label }}</button>
    @unless ($single)
        <span class="quick">
            @php($q = fn ($f, $t) => request()->fullUrlWithQuery(['from' => $f, 'to' => $t, 'page' => null]))
            <a href="{{ $q(today()->format('Y-m-d'), today()->format('Y-m-d')) }}">Hari ini</a>
            <a href="{{ $q(now()->startOfMonth()->format('Y-m-d'), today()->format('Y-m-d')) }}">Bulan ini</a>
            <a href="{{ $q(now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'), now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d')) }}">Bulan lalu</a>
            <a href="{{ $q(now()->startOfYear()->format('Y-m-d'), today()->format('Y-m-d')) }}">Tahun ini</a>
        </span>
    @endunless
    <span class="filters__end">
        @if ($csv)<a class="btn btn--ghost btn--sm" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Unduh CSV</a>@endif
        <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Cetak</button>
    </span>
</form>
