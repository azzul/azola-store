<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index()
    {
        $usage = Product::selectRaw('unit, COUNT(*) as n')->groupBy('unit')->pluck('n', 'unit');
        $conv = UnitConversion::selectRaw('unit, COUNT(*) as n')->groupBy('unit')->pluck('n', 'unit');

        return view('admin.units.index', ['units' => Unit::orderBy('name')->get(), 'usage' => $usage, 'conv' => $conv]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:20', Rule::unique('units', 'name')], 'note' => ['nullable', 'string', 'max:120']]);
        Unit::create($data);

        return back()->with('ok', 'Satuan ditambahkan.');
    }

    /** Ganti nama satuan: produk dan konversi yang memakainya ikut berubah. */
    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:20', Rule::unique('units', 'name')->ignore($unit->id)], 'note' => ['nullable', 'string', 'max:120']]);

        if ($data['name'] !== $unit->name) {
            Product::where('unit', $unit->name)->update(['unit' => $data['name']]);
            UnitConversion::where('unit', $unit->name)->update(['unit' => $data['name']]);
        }
        $unit->update($data);

        return back()->with('ok', 'Satuan diperbarui.');
    }

    public function destroy(Unit $unit)
    {
        if (Product::where('unit', $unit->name)->exists() || UnitConversion::where('unit', $unit->name)->exists()) {
            return back()->withErrors(['unit' => 'Satuan "'.$unit->name.'" masih dipakai produk atau konversi.']);
        }
        $unit->delete();

        return back()->with('ok', 'Satuan dihapus.');
    }
}
