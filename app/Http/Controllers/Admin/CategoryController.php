<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', ['categories' => Category::withCount('products')->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = $base = Str::slug($data['name']) ?: 'kategori';
        for ($i = 2; Category::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        Category::create($data + ['slug' => $slug, 'sort_order' => $data['sort_order'] ?? 0]);

        return back()->with('ok', 'Kategori ditambahkan.');
    }

    public function destroy(Category $category)
    {
        // Produk tidak ikut terhapus: kolom category_id jadi kosong (nullOnDelete).
        $category->delete();

        return back()->with('ok', 'Kategori dihapus. Produknya tetap ada, tanpa kategori.');
    }
}
