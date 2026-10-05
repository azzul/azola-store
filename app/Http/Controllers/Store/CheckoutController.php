<?php

namespace App\Http\Controllers\Store;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Cart;
use App\Support\Qty;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(private Cart $cart, private OrderService $orders) {}

    public function show(Request $request)
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index');
        }

        // uuid dibuat sekali per sesi checkout: klik "Pesan" dua kali tidak membuat dua pesanan.
        if (! $request->session()->has('checkout_uuid')) {
            $request->session()->put('checkout_uuid', (string) Str::uuid());
        }

        return view('store.checkout', [
            'seo' => Seo::page(['title' => 'Checkout', 'robots' => 'noindex,nofollow']),
            'lines' => $lines,
            'subtotal' => $this->cart->subtotal(),
            'methods' => config('store.web_payment_methods'),
        ]);
    }

    public function store(Request $request)
    {
        if (filled($request->input('website'))) { // honeypot untuk bot
            return redirect()->route('home');
        }

        $methods = array_keys(config('store.web_payment_methods'));

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'delivery_method' => ['required', Rule::in(['pickup', 'ship'])],
            'customer_address' => ['required_if:delivery_method,ship', 'nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::in($methods)],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'customer_address.required_if' => 'Alamat wajib diisi untuk pengiriman.',
            'customer_phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
        ]);

        $lines = $this->cart->lines();
        if ($lines->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $uuid = $request->session()->get('checkout_uuid') ?: (string) Str::uuid();

        try {
            [$order] = $this->orders->create($data + [
                'uuid' => $uuid,
                'shipping_fee' => Cart::shippingFor($data['delivery_method'], $this->cart->subtotal()),
                'items' => $lines->map(fn ($line) => [
                    'product_id' => $line['product']->id,
                    'qty' => Qty::fromMilli($line['milli']),
                ])->all(),
            ], 'web');
        } catch (InsufficientStockException $e) {
            return redirect()->route('cart.index')->withErrors(['cart' => $e->getMessage().' Kurangi jumlahnya lalu coba lagi.']);
        }

        $this->cart->clear();
        $request->session()->forget('checkout_uuid');

        return redirect()->route('order.show', $order->uuid);
    }

    public function order(string $uuid)
    {
        $order = Order::with('items')->where('uuid', $uuid)->firstOrFail();

        return view('store.order', [
            'seo' => Seo::page(['title' => 'Pesanan '.$order->number, 'robots' => 'noindex,nofollow']),
            'order' => $order,
            'methods' => config('store.web_payment_methods'),
        ]);
    }
}
