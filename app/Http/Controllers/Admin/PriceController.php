<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $products = Product::with(['levelPrices', 'category'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
            ->when($request->filled('category'), fn ($w) => $w->where('category_id', $request->query('category')))
            ->orderBy('name')->paginate(40)->withQueryString();

        return view('admin.prices.index', [
            'products' => $products, 'q' => $q,
            'levels' => PriceLevel::where('is_default', false)->orderBy('sort_order')->orderBy('id')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    /** Simpan satu halaman sekaligus: harga dasar (Ecer) dan harga per level. Kolom level kosong = pakai harga Ecer. */
    public function update(Request $request)
    {
        $request->validate([
            'base' => ['array'], 'base.*' => ['nullable', 'integer', 'min:0'],
            'level' => ['array'], 'level.*.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $changed = 0;
        foreach ((array) $request->input('base', []) as $id => $price) {
            if ($price === null || $price === '') {
                continue;
            }
            $product = Product::find($id);
            if ($product && (int) $product->price !== (int) $price) {
                $product->update(['price' => (int) $price]);
                $changed++;
            }
        }

        $levelIds = PriceLevel::where('is_default', false)->pluck('id')->all();
        foreach ((array) $request->input('level', []) as $productId => $prices) {
            foreach ((array) $prices as $levelId => $price) {
                if (! in_array((int) $levelId, $levelIds, true) || ! Product::whereKey($productId)->exists()) {
                    continue;
                }
                if ($price === null || $price === '') {
                    ProductPrice::where(['product_id' => $productId, 'price_level_id' => $levelId])->delete();
                } else {
                    ProductPrice::updateOrCreate(['product_id' => $productId, 'price_level_id' => $levelId], ['price' => (int) $price]);
                    $changed++;
                }
            }
        }

        return back()->with('ok', 'Harga disimpan.');
    }

    public function storeLevel(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', Rule::unique('price_levels', 'name')]]);
        PriceLevel::create($data + ['sort_order' => (int) PriceLevel::max('sort_order') + 1]);

        return back()->with('ok', 'Level harga "'.$data['name'].'" ditambahkan. Isi harganya di tabel, lalu pilih level itu di data customer.');
    }

    public function destroyLevel(PriceLevel $level)
    {
        if ($level->is_default) {
            return back()->withErrors(['level' => 'Level Ecer adalah harga dasar dan tidak bisa dihapus.']);
        }
        $level->delete();

        return back()->with('ok', 'Level harga dihapus.');
    }
}
