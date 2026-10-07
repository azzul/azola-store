<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\LineEditor;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /** Pencarian produk untuk editor baris: nama, SKU, atau barcode (termasuk barcode satuan besar). */
    public function products(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $level = (int) $request->query('level') ?: null;

        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        $products = Product::with('unitConversions')
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")->orWhere('barcode', $q)
                ->orWhere('variant_name', 'like', "%{$q}%")
                ->orWhereHas('unitConversions', fn ($c) => $c->where('barcode', $q)))
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN sku = ? OR barcode = ? THEN 0 ELSE 1 END', [$q, $q])->orderBy('name')
            ->limit(12)->get();

        return response()->json($products->map(fn (Product $p) => LineEditor::product($p, $level))->values());
    }
}
