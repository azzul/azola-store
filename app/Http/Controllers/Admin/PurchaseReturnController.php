<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use App\Support\LineEditor;
use App\Support\Qty;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));

        $returns = PurchaseReturn::with('supplier', 'purchase')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->filled('supplier'), fn ($w) => $w->where('supplier_id', $request->query('supplier')))
            ->latest('date')->latest('id')->paginate(30)->withQueryString();

        return view('admin.purchase-returns.index', [
            'returns' => $returns, 'from' => $from, 'to' => $to,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $purchase = $request->filled('purchase') ? Purchase::with('items')->find($request->query('purchase')) : null;
        $rows = old('items');

        if ($rows === null && $purchase) {
            // Isi awal: sisa yang masih bisa diretur per barang, memakai harga beli pada faktur.
            $returned = [];
            foreach (PurchaseReturn::where('purchase_id', $purchase->id)->where('status', 'posted')->with('items')->get() as $r) {
                foreach ($r->items as $i) {
                    $returned[$i->product_id] = ($returned[$i->product_id] ?? 0) + Qty::toMilli($i->base_qty);
                }
            }
            $rows = $purchase->items->map(function ($i) use ($returned) {
                $left = Qty::toMilli($i->base_qty) - ($returned[$i->product_id] ?? 0);

                return $left > 0 ? ['product_id' => $i->product_id, 'unit' => $i->product?->unit, 'qty' => 0, 'price' => (int) round($i->net_total * 1000 / max(1, Qty::toMilli($i->base_qty)))] : null;
            })->filter()->values()->all();
        }

        return view('admin.purchase-returns.form', [
            'purchase' => $purchase, 'initial' => LineEditor::hydrate($rows ?? []),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get(),
            'supplierId' => $request->query('supplier', $purchase?->supplier_id),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'], 'purchase_id' => ['nullable', 'exists:purchases,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'], 'date' => ['required', 'date', 'before_or_equal:today'],
            'settlement' => ['required', 'in:payable,cash,bank,receivable'], 'reason' => ['nullable', 'string', 'max:200'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.unit' => ['nullable', 'string', 'max:20'], 'items.*.qty' => ['required', 'numeric', 'min:0'],
            'items.*.price' => ['required', 'integer', 'min:0'],
        ]);

        $return = $this->purchases->createReturn($data, $request->user());

        return redirect()->route('admin.purchase-returns.show', $return)->with('ok', 'Retur '.$return->number.' tersimpan. Stok berkurang dan jurnal terbentuk.');
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        return view('admin.purchase-returns.show', ['return' => $purchaseReturn->load('items.product', 'supplier', 'purchase', 'warehouse', 'journals.lines.account')]);
    }

    public function cancel(Request $request, PurchaseReturn $purchaseReturn)
    {
        $this->purchases->cancelReturn($purchaseReturn, $request->user());

        return redirect()->route('admin.purchase-returns.show', $purchaseReturn)->with('ok', 'Retur dibatalkan: stok kembali dan jurnal dibalik.');
    }
}
