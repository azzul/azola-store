<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Journal;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\ReconciliationService;
use App\Support\Presenter;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Endpoint khusus admin: stok masuk, opname, buku besar, dan rekonsiliasi. */
class AdminController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'required_without:sku'],
            'sku' => ['nullable', 'string', 'required_without:product_id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'funding' => ['nullable', Rule::in(array_keys(InventoryService::FUNDING))],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $product = $this->product($data);
        $channel = $request->attributes->get('api_token')->channel();

        $this->inventory->receive($product, $data['qty'], $data['unit_cost'], $data['funding'] ?? 'cash', $request->user()->id, $channel, $data['note'] ?? null);

        return response()->json(['data' => Presenter::product($product->fresh(), true)], 201);
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'required_without:sku'],
            'sku' => ['nullable', 'string', 'required_without:product_id'],
            'delta' => ['required', 'numeric', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $product = $this->product($data);
        $channel = $request->attributes->get('api_token')->channel();

        try {
            $this->inventory->adjust($product, $data['delta'], $data['note'] ?? null, $request->user()->id, $channel);
        } catch (InsufficientStockException $e) {
            throw ValidationException::withMessages(['delta' => $e->getMessage()]);
        }

        return response()->json(['data' => Presenter::product($product->fresh(), true)]);
    }

    public function opname(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'required_without:sku'],
            'sku' => ['nullable', 'string', 'required_without:product_id'],
            'counted' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $product = $this->product($data);
        $channel = $request->attributes->get('api_token')->channel();

        $this->inventory->opname($product, $data['counted'], $data['note'] ?? null, $request->user()->id, $channel);

        return response()->json(['data' => Presenter::product($product->fresh(), true)]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return response()->json(['data' => Account::orderBy('code')->get()->map(fn (Account $a) => [
            'code' => $a->code,
            'key' => $a->key,
            'name' => $a->name,
            'type' => $a->type,
            'normal_balance' => $a->normal_balance,
            'balance' => $a->balance($request->query('from'), $request->query('to')),
        ])]);
    }

    public function journals(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = Journal::query()
            ->with('lines.account')
            ->when($request->query('from'), fn ($q, $v) => $q->where('date', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->where('date', '<=', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 50));

        return response()->json([
            'data' => $page->getCollection()->map(Presenter::journal(...))->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function reconciliation(ReconciliationService $service): JsonResponse
    {
        return response()->json($service->run());
    }

    private function product(array $data): Product
    {
        $query = isset($data['product_id'])
            ? Product::whereKey($data['product_id'])
            : Product::where('sku', $data['sku']);

        return $query->first() ?? throw ValidationException::withMessages(['product' => 'Produk tidak ditemukan.']);
    }
}
