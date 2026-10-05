<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $orders = Order::query()
            ->when($request->filled('status'), fn ($w) => $w->where('status', $request->query('status')))
            ->when($request->filled('channel'), fn ($w) => $w->where('channel', $request->query('channel')))
            ->when($request->filled('bayar'), fn ($w) => $w->where('payment_status', $request->query('bayar')))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('customer_name', 'like', "%{$q}%")->orWhere('customer_phone', 'like', "%{$q}%")))
            ->latest('ordered_at')->latest('id')->paginate(25)->withQueryString();

        return view('admin.orders.index', ['orders' => $orders, 'q' => $q]);
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', [
            'order' => $order->load('items', 'journals.lines.account', 'user'),
            'methods' => OrderService::METHODS,
        ]);
    }

    public function pay(Request $request, Order $order)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(OrderService::METHODS)],
        ]);

        $this->orders->recordPayment($order, (int) $data['amount'], $data['method'], $request->user());

        return back()->with('ok', 'Pembayaran dicatat, jurnal terbentuk.');
    }

    public function complete(Order $order)
    {
        $this->orders->complete($order);

        return back()->with('ok', 'Pesanan ditandai selesai.');
    }

    public function cancel(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $this->orders->cancel($order, $request->user(), $data['reason'] ?? null);

        return back()->with('ok', 'Pesanan dibatalkan: stok kembali dan jurnal dibalik.');
    }
}
