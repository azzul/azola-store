<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $productId = (int) $request->query('product') ?: null;

        $rows = UnitConversion::with('product')
            ->when($productId, fn ($w) => $w->where('product_id', $productId))
            ->when($q !== '', fn ($w) => $w->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
            ->orderBy('product_id')->orderBy('factor')->paginate(40)->withQueryString();

        return view('admin.conversions.index', [
            'rows' => $rows, 'q' => $q, 'productId' => $productId,
            'products' => Product::where('is_active', true)->orderBy('name')->limit(1000)->get(['id', 'sku', 'name', 'variant_name', 'unit']),
            'units' => Unit::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'unit' => ['required', 'string', 'max:20'],
            'factor' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'barcode' => ['nullable', 'string', 'max:60'],
            'price' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        if ($data['unit'] === $product->unit) {
            return back()->withInput()->withErrors(['unit' => 'Satuan konversi harus berbeda dari satuan dasar ('.$product->unit.').']);
        }
        if (UnitConversion::where('product_id', $product->id)->where('unit', $data['unit'])->exists()) {
            return back()->withInput()->withErrors(['unit' => 'Konversi untuk satuan ini sudah ada.']);
        }

        Unit::firstOrCreate(['name' => $data['unit']]);
        UnitConversion::create($data);

        return back()->with('ok', '1 '.$data['unit'].' = '.(float) $data['factor'].' '.$product->unit.' tersimpan.');
    }

    public function update(Request $request, UnitConversion $conversion)
    {
        $data = $request->validate([
            'factor' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'barcode' => ['nullable', 'string', 'max:60'],
            'price' => ['nullable', 'integer', 'min:0'],
        ]);
        $conversion->update($data);

        return back()->with('ok', 'Konversi diperbarui.');
    }

    public function destroy(UnitConversion $conversion)
    {
        $conversion->delete();

        return back()->with('ok', 'Konversi dihapus.');
    }
}
