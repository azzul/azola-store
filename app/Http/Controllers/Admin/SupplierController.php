<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $suppliers = Supplier::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))
            ->when($request->query('status') === 'off', fn ($w) => $w->where('is_active', false))
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.suppliers.index', ['suppliers' => $suppliers, 'q' => $q]);
    }

    public function create()
    {
        return view('admin.suppliers.form', ['supplier' => new Supplier(['is_active' => true, 'term_days' => 14])]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request));

        return redirect()->route('admin.suppliers.show', $supplier)->with('ok', 'Supplier ditambahkan.');
    }

    public function show(Supplier $supplier)
    {
        return view('admin.suppliers.show', [
            'supplier' => $supplier,
            'open' => Purchase::where('supplier_id', $supplier->id)->where('status', 'posted')->whereColumn('paid_total', '<', 'grand_total')->orderBy('due_date')->get(),
            'recent' => Purchase::where('supplier_id', $supplier->id)->latest('date')->latest('id')->limit(10)->get(),
            'payments' => SupplierPayment::where('supplier_id', $supplier->id)->latest('date')->latest('id')->limit(10)->get(),
            'returns' => PurchaseReturn::where('supplier_id', $supplier->id)->latest('date')->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('admin.suppliers.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.suppliers.show', $supplier)->with('ok', 'Supplier diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchases()->exists() || PurchaseReturn::where('supplier_id', $supplier->id)->exists() || SupplierPayment::where('supplier_id', $supplier->id)->exists()) {
            return back()->withErrors(['supplier' => 'Supplier sudah punya transaksi. Nonaktifkan saja agar riwayatnya tetap utuh.']);
        }
        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('ok', 'Supplier dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:20', \Illuminate\Validation\Rule::unique('suppliers', 'code')->ignore($request->route('supplier')?->id)],
            'name' => ['required', 'string', 'max:150'], 'contact' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'], 'term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'note' => ['nullable', 'string', 'max:500'],
        ]) + ['term_days' => (int) $request->input('term_days', 0)];
    }
}
