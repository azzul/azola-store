<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Cart;
use App\Support\Qty;
use App\Support\Seo;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private Cart $cart) {}

    public function index()
    {
        return view('store.cart', [
            'seo' => Seo::page(['title' => 'Keranjang', 'robots' => 'noindex,nofollow']),
            'lines' => $this->cart->lines(),
        ]);
    }

    public function add(Request $request, Product $product)
    {
        abort_unless($product->is_active && $product->is_online, 404);

        $data = $request->validate(['qty' => ['nullable', 'numeric', 'gt:0', 'max:100000']]);

        if (! $product->isInStock()) {
            return back()->withErrors(['cart' => $product->name.' sedang habis.']);
        }

        $this->cart->add($product->id, Qty::toMilli($data['qty'] ?? 1));

        return $request->boolean('langsung')
            ? redirect()->route('cart.index')
            : back()->with('added', $product->name.' masuk keranjang.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        foreach ($data['qty'] as $productId => $qty) {
            $this->cart->set((int) $productId, Qty::toMilli($qty ?? 0));
        }

        return redirect()->route('cart.index');
    }

    public function remove(int $product)
    {
        $this->cart->remove($product);

        return redirect()->route('cart.index');
    }
}
