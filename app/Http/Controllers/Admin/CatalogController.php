<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Etalase;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Services\InventoryService;
use App\Support\Images;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Katalog toko: produk (grup) -> variasi (SKU) + foto + etalase.
 * Stok dan harga beli tetap diurus per SKU di halaman Produk/SKU, supaya jurnal selalu ikut.
 */
class CatalogController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $groups = ProductGroup::with(['category', 'variants', 'images', 'etalases'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('brand', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)->orWhere('barcode', $q)));
            })
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.catalog.index', compact('groups', 'q'));
    }

    public function create()
    {
        return view('admin.catalog.form', $this->formData(new ProductGroup(['is_active' => true, 'is_online' => true])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $group = new ProductGroup($data['fields']);
        $group->slug = ProductGroup::uniqueSlug($data['fields']['name']);
        $group->auto = false;
        $group->save();
        $group->etalases()->sync($data['etalases']);

        return redirect()->route('admin.catalog.edit', $group)->with('ok', 'Produk dibuat. Sekarang tambahkan variasi (SKU) dan foto di bawah.');
    }

    public function edit(ProductGroup $group)
    {
        $group->load(['variants', 'images', 'etalases']);

        return view('admin.catalog.form', $this->formData($group));
    }

    public function update(Request $request, ProductGroup $group)
    {
        $data = $this->validated($request);

        $group->fill($data['fields']);
        if ($request->boolean('regenerate_slug') && $group->isDirty('name')) {
            $group->slug = ProductGroup::uniqueSlug($data['fields']['name'], $group->id);
        }
        // Setelah diedit di sini, grup tidak lagi otomatis mengikuti satu produk.
        $group->auto = false;
        $group->save();
        $group->etalases()->sync($data['etalases']);

        // Kategori dan nama tampil ikut ke semua variasi supaya kasir dan laporan konsisten.
        $group->variants()->update(['category_id' => $group->category_id]);

        return back()->with('ok', 'Perubahan disimpan.');
    }

    public function storeVariant(Request $request, ProductGroup $group)
    {
        $names = array_values(array_filter((array) $group->option_names));

        $data = $request->validate([
            'variant_name' => ['required', 'string', 'max:80'],
            'sku' => ['required', 'string', 'max:60', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:60'],
            'unit' => ['required', 'string', 'max:20'],
            'price' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:60'],
            'initial_qty' => ['nullable', 'numeric', 'min:0'],
            'initial_cost' => ['nullable', 'integer', 'min:0', 'required_with:initial_qty'],
        ]);

        $options = collect($names)->mapWithKeys(fn ($n) => [$n => trim((string) ($data['options'][$n] ?? ''))])->filter()->all();
        $name = $group->name.' '.$data['variant_name'];

        $product = Product::create([
            'group_id' => $group->id, 'category_id' => $group->category_id,
            'sku' => $data['sku'], 'barcode' => $data['barcode'] ?? null,
            'name' => $name, 'slug' => Product::uniqueSlug($name),
            'variant_name' => $data['variant_name'], 'options' => $options ?: null,
            'sort_order' => (int) $group->variants()->max('sort_order') + 1,
            'description' => $group->summary, 'unit' => $data['unit'], 'price' => $data['price'],
            'min_stock' => $data['min_stock'] ?? 0,
            'is_active' => $group->is_active, 'is_online' => $group->is_online, 'is_featured' => false,
        ]);

        $qty = $data['initial_qty'] ?? null;
        if ($qty !== null && $qty !== '' && (float) $qty > 0) {
            $this->inventory->receive($product, $qty, (int) $data['initial_cost'], 'equity', $request->user()->id, 'admin', 'Stok awal');
        } elseif (isset($data['initial_cost'])) {
            $product->forceFill(['cost' => (int) $data['initial_cost']])->save();
        }

        return back()->with('ok', "Variasi {$data['variant_name']} ditambahkan.");
    }

    public function uploadImages(Request $request, ProductGroup $group)
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ], [
            'photos.required' => 'Pilih minimal satu foto.',
            'photos.*.image' => 'File harus berupa gambar (JPG, PNG, atau WebP).',
            'photos.*.max' => 'Ukuran tiap foto maksimal 5 MB.',
        ]);

        $order = (int) $group->images()->max('sort_order');
        foreach ($request->file('photos') as $file) {
            try {
                $paths = Images::store($file);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['photos' => 'Salah satu foto tidak bisa diproses. Coba simpan ulang sebagai JPG.']);
            }

            $group->images()->create($paths + ['alt' => $group->name, 'sort_order' => ++$order]);
        }

        return back()->with('ok', count($request->file('photos')).' foto diunggah.');
    }

    public function updateImage(Request $request, ProductGroup $group, ProductImage $image)
    {
        abort_unless($image->product_group_id === $group->id, 404);

        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:160'],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('group_id', $group->id)],
        ]);

        $image->update(['alt' => $data['alt'] ?? null, 'product_id' => $data['product_id'] ?? null]);

        return back()->with('ok', 'Foto diperbarui.');
    }

    public function moveImage(Request $request, ProductGroup $group, ProductImage $image)
    {
        abort_unless($image->product_group_id === $group->id, 404);
        $direction = $request->input('to');

        $ids = $group->images()->pluck('id')->all();
        $i = array_search($image->id, $ids, true);
        $j = match ($direction) { 'first' => 0, 'left' => max(0, $i - 1), 'right' => min(count($ids) - 1, $i + 1), default => $i };

        array_splice($ids, $i, 1);
        array_splice($ids, $j, 0, [$image->id]);
        foreach ($ids as $order => $id) {
            ProductImage::whereKey($id)->update(['sort_order' => $order + 1]);
        }

        return back();
    }

    public function destroyImage(ProductGroup $group, ProductImage $image)
    {
        abort_unless($image->product_group_id === $group->id, 404);
        $image->delete();

        return back()->with('ok', 'Foto dihapus.');
    }

    private function formData(ProductGroup $group): array
    {
        return [
            'group' => $group,
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'etalases' => Etalase::orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    /** @return array{fields: array, etalases: array<int>} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'brand' => ['nullable', 'string', 'max:80'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'summary' => ['nullable', 'string', 'max:240'],
            'description' => ['nullable', 'string', 'max:8000'],
            'highlights' => ['nullable', 'string', 'max:2000'],
            'specs' => ['nullable', 'string', 'max:3000'],
            'option_names' => ['nullable', 'string', 'max:200'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'etalases' => ['nullable', 'array'],
            'etalases.*' => ['integer', 'exists:etalases,id'],
        ]);

        $lines = fn (?string $text) => collect(preg_split('/\R/', (string) $text))->map(fn ($l) => trim($l))->filter()->values();

        $specs = $lines($data['specs'] ?? '')->map(function ($line) {
            [$label, $value] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');

            return $value === '' ? null : ['label' => $label, 'value' => $value];
        })->filter()->values()->all();

        $fields = [
            'name' => $data['name'], 'brand' => $data['brand'] ?? null, 'category_id' => $data['category_id'] ?? null,
            'summary' => $data['summary'] ?? null, 'description' => $data['description'] ?? null,
            'highlights' => $lines($data['highlights'] ?? '')->take(8)->all() ?: null,
            'specs' => $specs ?: null,
            'option_names' => collect(explode(',', (string) ($data['option_names'] ?? '')))->map(fn ($n) => trim($n))->filter()->unique()->take(3)->values()->all() ?: null,
            'meta_title' => $data['meta_title'] ?? null, 'meta_description' => $data['meta_description'] ?? null,
            'is_active' => $request->boolean('is_active'), 'is_online' => $request->boolean('is_online'), 'is_featured' => $request->boolean('is_featured'),
        ];

        return ['fields' => $fields, 'etalases' => array_map('intval', $data['etalases'] ?? [])];
    }
}
