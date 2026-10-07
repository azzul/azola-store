<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use App\Support\Csv;
use App\Support\LineEditor;
use App\Support\Qty;
use App\Support\Rupiah;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    /** Laporan pembelian: filter periode, supplier, status bayar; ringkasan di atas tabel. */
    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $q = trim((string) $request->query('q'));

        $query = Purchase::with('supplier', 'warehouse')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->filled('supplier'), fn ($w) => $w->where('supplier_id', $request->query('supplier')))
            ->when($request->query('status') === 'cancelled', fn ($w) => $w->where('status', 'cancelled'), fn ($w) => $w->where('status', 'posted'))
            ->when($request->query('pay') === 'unpaid', fn ($w) => $w->whereColumn('paid_total', '<', 'grand_total'))
            ->when($request->query('pay') === 'paid', fn ($w) => $w->whereColumn('paid_total', '>=', 'grand_total'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('supplier_invoice', 'like', "%{$q}%")))
            ->latest('date')->latest('id');

        if ($request->query('export') === 'csv') {
            return Csv::download('pembelian-'.$from.'-'.$to.'.csv', ['Nomor', 'Tanggal', 'Supplier', 'Faktur supplier', 'Cara bayar', 'Total', 'Dibayar', 'Sisa hutang', 'Status'],
                (clone $query)->get()->map(fn (Purchase $p) => [$p->number, $p->date->format('Y-m-d'), $p->supplier?->name, $p->supplier_invoice, $p->payment_method, $p->grand_total, $p->paid_total, $p->outstanding(), $p->status]));
        }

        $summary = (clone $query)->reorder()->selectRaw('COUNT(*) as n, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(paid_total),0) as paid')->first();

        return view('admin.purchases.index', [
            'purchases' => $query->paginate(30)->withQueryString(), 'from' => $from, 'to' => $to, 'q' => $q, 'summary' => $summary,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $purchase = new Purchase(['date' => today(), 'payment_method' => 'cash', 'supplier_id' => $request->query('supplier'), 'warehouse_id' => Warehouse::mainId()]);

        return view('admin.purchases.form', $this->formData($purchase, old('items', [])));
    }

    public function store(Request $request)
    {
        $purchase = $this->purchases->create($this->validated($request), $request->user());

        return redirect()->route('admin.purchases.show', $purchase)->with('ok', 'Pembelian '.$purchase->number.' tersimpan. Stok, HPP, dan jurnal sudah diperbarui.');
    }

    public function show(Purchase $purchase)
    {
        return view('admin.purchases.show', [
            'purchase' => $purchase->load('items.product', 'supplier', 'warehouse', 'user', 'journals.lines.account', 'allocations.source'),
            'returns' => PurchaseReturn::where('purchase_id', $purchase->id)->get(),
        ]);
    }

    public function edit(Purchase $purchase)
    {
        if ($purchase->isCancelled()) {
            return redirect()->route('admin.purchases.show', $purchase)->withErrors(['purchase' => 'Faktur yang dibatalkan tidak bisa diubah.']);
        }

        $purchase->load('items');
        $rows = old('items', $purchase->items->map(fn ($i) => ['product_id' => $i->product_id, 'unit' => $i->unit, 'qty' => (float) $i->qty, 'price' => $i->price])->all());

        return view('admin.purchases.form', $this->formData($purchase, $rows));
    }

    public function update(Request $request, Purchase $purchase)
    {
        $this->purchases->update($purchase, $this->validated($request), $request->user());

        return redirect()->route('admin.purchases.show', $purchase)->with('ok', 'Faktur '.$purchase->number.' diperbarui. Stok, HPP, dan jurnal dikoreksi.');
    }

    public function cancel(Request $request, Purchase $purchase)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        $this->purchases->cancel($purchase, $request->user(), $data['reason'] ?? null);

        return redirect()->route('admin.purchases.show', $purchase)->with('ok', 'Faktur dibatalkan: stok dikurangi dan jurnal dibalik.');
    }

    private function formData(Purchase $purchase, array $rows): array
    {
        $allocated = $purchase->exists ? (int) $purchase->allocations()->sum('amount') : 0;

        return [
            'purchase' => $purchase, 'initial' => LineEditor::hydrate($rows),
            'suppliers' => Supplier::where('is_active', true)->orWhere('id', $purchase->supplier_id)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get(),
            'downPayment' => $purchase->exists && $purchase->payment_method === 'credit' ? $purchase->paid_total - $allocated : 0,
            'allocated' => $allocated,
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'in:cash,bank,credit'],
            'supplier_invoice' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:500'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'down_payment' => ['nullable', 'integer', 'min:0'],
            'down_payment_via' => ['nullable', 'in:cash,bank'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'integer', 'min:0'],
        ], [
            'items.required' => 'Tambahkan minimal satu barang.',
            'date.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
        ]);
    }
}
