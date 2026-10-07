<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::orderByDesc('is_main')->orderBy('name')->get();
        $stock = DB::table('stock_balances')->selectRaw('warehouse_id, COUNT(*) as items, SUM(qty) as qty')->where('qty', '>', 0)->groupBy('warehouse_id')->get()->keyBy('warehouse_id');

        return view('admin.warehouses.index', [
            'warehouses' => $warehouses, 'stock' => $stock,
            'mainItems' => Product::where('stock_qty', '>', 0)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')],
            'name' => ['required', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:200'],
        ]);
        Warehouse::create($data);

        return back()->with('ok', 'Gudang ditambahkan.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($warehouse->id)],
            'name' => ['required', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:200'],
        ]);

        $active = $warehouse->is_main ? true : $request->boolean('is_active');
        if (! $active && DB::table('stock_balances')->where('warehouse_id', $warehouse->id)->where('qty', '>', 0)->exists()) {
            return back()->withErrors(['warehouse' => 'Gudang masih berisi stok. Pindahkan dulu lewat alih gudang.']);
        }

        $warehouse->update($data + ['is_active' => $active]);

        return back()->with('ok', 'Gudang diperbarui.');
    }
}
