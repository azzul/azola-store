<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Images;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $products = Product::with('category')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")->orWhere('barcode', $q)))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.products.index', compact('products', 'q'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product(['unit' => 'pcs', 'is_active' => true, 'is_online' => true]),
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'fundings' => InventoryService::FUNDING,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $initialQty = $request->input('initial_qty');
        $request->validate([
            'initial_qty' => ['nullable', 'numeric', 'min:0'],
            'initial_cost' => ['nullable', 'integer', 'min:0', 'required_with:initial_qty'],
            'funding' => ['nullable', Rule::in(array_keys(InventoryService::FUNDING))],
        ]);

        $product = new Product($data);
        $product->slug = Product::uniqueSlug($data['name']);
        $product->save();
        $this->storePhoto($request, $product);

        if ($initialQty !== null && $initialQty !== '' && (float) $initialQty > 0) {
            $this->inventory->receive($product, $initialQty, (int) $request->input('initial_cost'), $request->input('funding', 'equity'), $request->user()->id, 'admin', 'Stok awal');
        } elseif ($request->filled('initial_cost')) {
            $product->forceFill(['cost' => (int) $request->input('initial_cost')])->save();
        }

        return redirect()->route('admin.products.edit', $product)->with('ok', 'Produk disimpan.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'fundings' => InventoryService::FUNDING,
            'movements' => StockMovement::where('product_id', $product->id)->latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);

        $product->fill($data);
        if ($product->isDirty('name') && $request->boolean('regenerate_slug')) {
            $product->slug = Product::uniqueSlug($data['name'], $product->id);
        }
        $product->save();
        $this->storePhoto($request, $product);

        return back()->with('ok', 'Perubahan disimpan.');
    }

    public function receive(Request $request, Product $product)
    {
        $data = $request->validate([
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'funding' => ['required', Rule::in(array_keys(InventoryService::FUNDING))],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $this->inventory->receive($product, $data['qty'], $data['unit_cost'], $data['funding'], $request->user()->id, 'admin', $data['note'] ?? null);

        return back()->with('ok', 'Stok masuk dicatat, jurnal pembelian terbentuk.');
    }

    public function adjust(Request $request, Product $product)
    {
        $data = $request->validate(['delta' => ['required', 'numeric', 'not_in:0'], 'note' => ['required', 'string', 'max:200']]);

        try {
            $this->inventory->adjust($product, $data['delta'], $data['note'], $request->user()->id, 'admin');
        } catch (InsufficientStockException $e) {
            throw ValidationException::withMessages(['delta' => $e->getMessage()]);
        }

        return back()->with('ok', 'Penyesuaian stok dicatat.');
    }

    public function opname(Request $request, Product $product)
    {
        $data = $request->validate(['counted' => ['required', 'numeric', 'min:0'], 'note' => ['nullable', 'string', 'max:200']]);

        $this->inventory->opname($product, $data['counted'], $data['note'] ?? null, $request->user()->id, 'admin');

        return back()->with('ok', 'Hasil opname dicatat.');
    }

    /**
     * Foto dari form SKU masuk ke galeri produknya (ukuran kartu, besar, miniatur dibuat otomatis).
     * Produk satu-SKU: foto menggantikan yang lama. Produk bervariasi: foto ditambahkan sebagai foto variasi ini.
     */
    private function storePhoto(Request $request, Product $product): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        $product->refresh();
        $group = $product->group;
        $paths = Images::store($request->file('image'));

        if ($group->auto) {
            $group->images()->get()->each->delete();
        }
        $group->images()->create($paths + [
            'alt' => $product->name,
            'product_id' => $group->auto ? null : $product->id,
            'sort_order' => $group->auto ? 1 : (int) $group->images()->max('sort_order') + 1,
        ]);

        // Kasir/API memakai foto ukuran kartu.
        $product->forceFill(['image_path' => $paths['card_path']])->saveQuietly();
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['required', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($product?->id)],
            'barcode' => ['nullable', 'string', 'max:60'],
            'variant_name' => ['nullable', 'string', 'max:80'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'price' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        unset($data['image']);
        \App\Models\Unit::firstOrCreate(['name' => $data['unit']]);
        $data['min_stock'] = $data['min_stock'] ?? 0;
        foreach (['is_active', 'is_online', 'is_featured'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }
}
