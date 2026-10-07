<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Http\Request;

/** Master variasi: ringkasan atribut (Ukuran, Warna, ...) dan seluruh SKU variasi. Pengeditan di Katalog toko. */
class VariationController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $attributes = [];
        foreach (ProductGroup::whereNotNull('option_names')->get(['id', 'name', 'option_names']) as $group) {
            foreach ((array) $group->option_names as $name) {
                $attributes[$name]['groups'][$group->id] = $group->name;
            }
        }
        foreach (Product::whereNotNull('options')->get(['id', 'options']) as $product) {
            foreach ((array) $product->options as $name => $value) {
                $attributes[$name]['values'][$value] = ($attributes[$name]['values'][$value] ?? 0) + 1;
            }
        }
        ksort($attributes);

        $variants = Product::with('group')
            ->whereHas('group', fn ($g) => $g->where('auto', false))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")->orWhere('variant_name', 'like', "%{$q}%")))
            ->orderBy('group_id')->orderBy('sort_order')->orderBy('id')->paginate(40)->withQueryString();

        return view('admin.variations.index', ['attributes' => $attributes, 'variants' => $variants, 'q' => $q]);
    }
}
