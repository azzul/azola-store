@extends('layouts.store')

@section('content')
    <div class="wrap page">
        <h1 class="page__title">Keranjang</h1>

        @if ($errors->has('cart'))<p class="alert" role="alert">{{ $errors->first('cart') }}</p>@endif

        @if ($lines->isEmpty())
            <div class="empty">
                <p><strong>Keranjang masih kosong.</strong></p>
                <p><a class="btn" href="{{ route('shop.index') }}">Mulai belanja</a></p>
            </div>
        @else
            <form method="post" action="{{ route('cart.update') }}" class="cart">
                @csrf
                @method('PATCH')
                <table class="table">
                    <thead><tr><th>Barang</th><th class="num">Harga</th><th>Jumlah</th><th class="num">Subtotal</th><th><span class="sr">Hapus</span></th></tr></thead>
                    <tbody>
                    @foreach ($lines as $line)
                        @php($p = $line['product'])
                        <tr>
                            <td>
                                <a href="{{ $p->url() }}"><strong>{{ $p->name }}</strong></a>
                                @if ($line['short'])<br><span class="warn">Stok tersisa {{ \App\Support\Qty::pretty($p->stock_qty) }} {{ $p->unit }}. Kurangi jumlahnya.</span>@endif
                            </td>
                            <td class="num">{{ \App\Support\Rupiah::format($p->price) }}</td>
                            <td><label class="sr" for="q{{ $p->id }}">Jumlah {{ $p->name }}</label>
                                <input id="q{{ $p->id }}" class="qty" name="qty[{{ $p->id }}]" type="number" min="0" step="any" inputmode="decimal" value="{{ \App\Support\Qty::pretty(\App\Support\Qty::fromMilli($line['milli'])) }}"> {{ $p->unit }}</td>
                            <td class="num">{{ \App\Support\Rupiah::format($line['gross']) }}</td>
                            <td class="num"><button class="link" type="submit" formaction="{{ route('cart.remove', $p->id) }}" formmethod="post" name="_method" value="DELETE">Hapus</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                @php($subtotal = $lines->sum('gross'))
                <div class="cart__foot">
                    <p class="cart__free">
                        @if ((int) config('store.shipping.free_over') > 0)
                            Gratis ongkir untuk belanja mulai {{ \App\Support\Rupiah::format(config('store.shipping.free_over')) }}.
                        @endif
                    </p>
                    <div class="cart__sum">
                        <p>Subtotal <strong>{{ \App\Support\Rupiah::format($subtotal) }}</strong></p>
                        <div class="cart__actions">
                            <button class="btn btn--ghost" type="submit">Perbarui jumlah</button>
                            <a class="btn" href="{{ route('checkout.show') }}">Lanjut ke checkout</a>
                        </div>
                    </div>
                </div>
            </form>
        @endif
    </div>
@endsection
