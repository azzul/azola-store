@props(['name' => 'items', 'mode' => 'price', 'priceFrom' => 'cost', 'initial' => [], 'placeholder' => 'Ketik nama, SKU, atau scan barcode lalu Enter'])
<div class="lines" data-lines data-name="{{ $name }}" data-mode="{{ $mode }}" data-price-from="{{ $priceFrom }}"
     data-lookup="{{ route('admin.lookup.products') }}" data-initial='@json($initial)'>
    <div class="lines__find">
        <label for="lines-{{ $name }}">Tambah barang</label>
        <input id="lines-{{ $name }}" class="lines__search" type="search" autocomplete="off" placeholder="{{ $placeholder }}" role="combobox" aria-expanded="false" aria-controls="lines-{{ $name }}-list">
        <ul id="lines-{{ $name }}-list" class="lines__results" role="listbox" hidden></ul>
    </div>
    <div class="scroll">
        <table class="lines__table">
            <thead>
            <tr>
                <th>Barang</th><th>Satuan</th><th>{{ $mode === 'delta' ? 'Selisih (+/-)' : 'Jumlah' }}</th>
                @if ($mode === 'price')<th>{{ $priceFrom === 'cost' ? 'Harga beli' : 'Harga jual' }}</th><th class="num">Subtotal</th>@endif
                <th><span class="sr-only">Hapus</span></th>
            </tr>
            </thead>
            <tbody class="lines__body"></tbody>
            <tbody><tr class="lines__empty"><td colspan="6" class="muted">Belum ada barang. Cari di kolom atas.</td></tr></tbody>
        </table>
    </div>
</div>
