@extends('layouts.store')

@section('content')
    <div class="wrap page">
        <h1 class="page__title">Checkout</h1>

        @if ($errors->any())
            <div class="alert" role="alert">
                <strong>Periksa kembali isian berikut:</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="post" action="{{ route('checkout.store') }}" class="checkout" id="checkout" novalidate>
            @csrf
            <div class="hp" aria-hidden="true"><label>Jangan diisi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <div class="checkout__form">
                <fieldset>
                    <legend>Data pemesan</legend>
                    <div class="field"><label for="customer_name">Nama lengkap</label>
                        <input id="customer_name" name="customer_name" value="{{ old('customer_name', $me->name ?? '') }}" required autocomplete="name"></div>
                    <div class="field"><label for="customer_phone">Nomor telepon atau WhatsApp</label>
                        <input id="customer_phone" name="customer_phone" type="tel" value="{{ old('customer_phone', $me->phone ?? '') }}" required autocomplete="tel"></div>
                    <div class="field"><label for="customer_email">Email <span class="muted">(boleh dikosongkan)</span></label>
                        <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email', $me->email ?? '') }}" autocomplete="email"></div>
                </fieldset>

                <fieldset>
                    <legend>Pengambilan barang</legend>
                    <label class="choice"><input type="radio" name="delivery_method" value="pickup" @checked(old('delivery_method', 'pickup') === 'pickup')> <span>Ambil di toko <small>Gratis</small></span></label>
                    <label class="choice"><input type="radio" name="delivery_method" value="ship" @checked(old('delivery_method') === 'ship')> <span>Kirim ke alamat
                        <small>{{ \App\Support\Rupiah::format(config('store.shipping.flat')) }}@if ((int) config('store.shipping.free_over') > 0), gratis mulai {{ \App\Support\Rupiah::format(config('store.shipping.free_over')) }}@endif</small></span></label>
                    <div class="field" id="address-field"><label for="customer_address">Alamat pengiriman</label>
                        <textarea id="customer_address" name="customer_address" rows="3">{{ old('customer_address', $me->address ?? '') }}</textarea></div>
                </fieldset>

                <fieldset>
                    <legend>Pembayaran</legend>
                    @foreach ($methods as $key => $label)
                        <label class="choice"><input type="radio" name="payment_method" value="{{ $key }}" @checked(old('payment_method', array_key_first($methods)) === $key)> <span>{{ $label }}</span></label>
                    @endforeach
                    <div class="field"><label for="notes">Catatan untuk toko <span class="muted">(boleh dikosongkan)</span></label>
                        <textarea id="notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
                </fieldset>
            </div>

            <aside class="summary" aria-labelledby="sum-title">
                <h2 id="sum-title">Ringkasan</h2>
                <ul class="summary__lines">
                    @foreach ($lines as $line)
                        <li><span>{{ $line['product']->name }} <small>x {{ \App\Support\Qty::pretty(\App\Support\Qty::fromMilli($line['milli'])) }}</small></span><span>{{ \App\Support\Rupiah::format($line['gross']) }}</span></li>
                    @endforeach
                </ul>
                <dl class="summary__tot">
                    <div><dt>Subtotal</dt><dd>{{ \App\Support\Rupiah::format($subtotal) }}</dd></div>
                    <div><dt>Ongkos kirim</dt><dd id="ship-fee" data-flat="{{ (int) config('store.shipping.flat') }}" data-free="{{ (int) config('store.shipping.free_over') }}" data-sub="{{ $subtotal }}">Gratis</dd></div>
                    <div class="summary__grand"><dt>Total</dt><dd id="grand">{{ \App\Support\Rupiah::format($subtotal) }}</dd></div>
                </dl>
                <button class="btn btn--block" type="submit">Pesan sekarang</button>
                <p class="muted summary__note">Stok dipesankan untukmu begitu pesanan dibuat. Total akhir dihitung ulang oleh toko dari harga terbaru.</p>
            </aside>
        </form>
    </div>

    <script>
        (function () {
            var form = document.getElementById('checkout');
            var fee = document.getElementById('ship-fee');
            var grand = document.getElementById('grand');
            var address = document.getElementById('address-field');
            var rp = function (n) { return 'Rp' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
            function update() {
                var ship = form.querySelector('input[name=delivery_method]:checked').value === 'ship';
                var sub = +fee.dataset.sub, flat = +fee.dataset.flat, free = +fee.dataset.free;
                var cost = ship && !(free > 0 && sub >= free) ? flat : 0;
                fee.textContent = cost ? rp(cost) : 'Gratis';
                grand.textContent = rp(sub + cost);
                address.hidden = !ship;
            }
            form.addEventListener('change', update);
            update();
        })();
    </script>
@endsection
