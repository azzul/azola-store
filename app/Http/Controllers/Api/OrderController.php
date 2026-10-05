<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    /**
     * Dipakai kasir desktop & Android. "uuid" dibuat oleh klien: bila request dikirim ulang
     * (koneksi putus, antrean offline), pesanan yang sama dikembalikan tanpa mengurangi stok lagi.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['nullable', 'integer', 'required_without:items.*.sku'],
            'items.*.sku' => ['nullable', 'string', 'required_without:items.*.product_id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.discount' => ['nullable', 'integer', 'min:0'],
            'order_discount' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', Rule::in(OrderService::METHODS)],
            'paid_total' => ['nullable', 'integer', 'min:0'],
            'shipping_fee' => ['nullable', 'integer', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'delivery_method' => ['nullable', Rule::in(['pickup', 'ship'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'ordered_at' => ['nullable', 'date'],
        ]);

        $channel = $request->attributes->get('api_token')->channel();

        try {
            [$order, $created] = $this->orders->create($data, $channel, $request->user());
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['items' => [$e->getMessage()]],
                'stock_conflict' => $e->context(),
            ], 422);
        }

        return response()->json(
            ['data' => Presenter::order($order), 'duplicate' => ! $created],
            $created ? 201 : 200,
        );
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'date'],
            'channel' => ['nullable', Rule::in(Order::CHANNELS)],
            'status' => ['nullable', 'in:pending,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = Order::query()
            ->with('items')
            ->when($request->query('since'), fn ($q, $v) => $q->where('updated_at', '>=', $v))
            ->when($request->query('channel'), fn ($q, $v) => $q->where('channel', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 50));

        return response()->json([
            'data' => $page->getCollection()->map(Presenter::order(...))->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json(['data' => Presenter::order(Order::with('items')->where('uuid', $uuid)->firstOrFail())]);
    }

    public function pay(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(OrderService::METHODS)],
        ]);

        $order = Order::where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => Presenter::order(
            $this->orders->recordPayment($order, $data['amount'], $data['method'], $request->user()),
        )]);
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $order = Order::where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => Presenter::order(
            $this->orders->cancel($order, $request->user(), $request->input('reason')),
        )]);
    }
}
