<?php

namespace App\Models;

use App\Support\Qty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    /**
     * stock_qty dan cost sengaja TIDAK mass-assignable: hanya boleh berubah lewat
     * StockService / InventoryService supaya selalu ada catatan mutasi dan jurnal.
     */
    protected $fillable = [
        'group_id', 'variant_name', 'options', 'sort_order', 'category_id', 'sku', 'barcode', 'name', 'slug', 'description', 'unit',
        'price', 'min_stock', 'image_path', 'is_active', 'is_online', 'is_featured',
        'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'options' => 'array',
            'cost' => 'integer',
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'group_id');
    }

    /**
     * Setiap produk selalu punya grup: kalau belum ada, dibuatkan grup 1:1 yang ikut berubah bersama produknya.
     * Grup buatan admin (auto = false) tidak ditimpa; hanya rentang harganya yang dihitung ulang.
     */
    protected static function booted(): void
    {
        static::saved(function (Product $product) {
            $created = $product->wasRecentlyCreated;

            if (! $product->group_id) {
                $group = ProductGroup::create([
                    'category_id' => $product->category_id, 'name' => $product->name,
                    'slug' => ProductGroup::uniqueSlug($product->slug ?: $product->name),
                    'description' => $product->description, 'auto' => true,
                    'is_active' => $product->is_active ?? true, 'is_online' => $product->is_online ?? true, 'is_featured' => $product->is_featured ?? false,
                    'meta_title' => $product->meta_title, 'meta_description' => $product->meta_description,
                    'price_min' => (int) $product->price, 'price_max' => (int) $product->price,
                ]);
                $product->forceFill(['group_id' => $group->id])->saveQuietly();
                $product->setRelation('group', $group);

                return;
            }

            $group = $product->group;
            if (! $group) {
                return;
            }

            if ($group->auto && ($created || $product->wasChanged(['name', 'category_id', 'description', 'is_active', 'is_online', 'is_featured', 'meta_title', 'meta_description', 'slug']))) {
                $group->forceFill([
                    'category_id' => $product->category_id, 'name' => $product->name, 'description' => $product->description,
                    'is_active' => $product->is_active ?? true, 'is_online' => $product->is_online ?? true, 'is_featured' => $product->is_featured ?? false,
                    'meta_title' => $product->meta_title, 'meta_description' => $product->meta_description,
                ]);
                if ($product->wasChanged('slug')) {
                    $group->slug = ProductGroup::uniqueSlug($product->slug, $group->id);
                }
                $group->save();
            }

            if ($created || $product->wasChanged(['price', 'group_id', 'is_active', 'is_online'])) {
                $group->refreshPrices();
            }
        });

        static::deleted(function (Product $product) {
            $group = $product->group;
            if ($group && $group->variants()->doesntExist() && $group->auto) {
                $group->delete();
            } elseif ($group) {
                $group->refreshPrices();
            }
        });
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_online', true);
    }

    public function qtyMilli(): int
    {
        return Qty::toMilli($this->stock_qty);
    }

    public function minMilli(): int
    {
        return Qty::toMilli($this->min_stock);
    }

    public function isInStock(): bool
    {
        return $this->qtyMilli() > 0;
    }

    public function isLowStock(): bool
    {
        return $this->qtyMilli() > 0 && $this->qtyMilli() <= $this->minMilli();
    }

    /** out | low | ok -> dipakai badge di web, dashboard, dan klien POS. */
    public function stockState(): string
    {
        if (! $this->isInStock()) {
            return 'out';
        }

        $threshold = $this->minMilli() > 0
            ? $this->minMilli()
            : Qty::toMilli(config('store.public_low_stock'));

        return $this->qtyMilli() <= $threshold ? 'low' : 'ok';
    }

    public function publicStockLabel(): string
    {
        return match ($this->stockState()) {
            'out' => 'Stok habis',
            'low' => 'Sisa '.Qty::pretty($this->stock_qty).' '.$this->unit,
            default => 'Stok tersedia',
        };
    }

    public function url(): string
    {
        $group = $this->group ?? ($this->group_id ? $this->group()->first() : null);

        return $group ? $group->url() : route('shop.index');
    }

    /** Foto untuk kasir/API: foto sendiri, atau sampul grupnya bila sudah dimuat. */
    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->relationLoaded('group') ? $this->group?->imageUrl('card') : null;
    }

    public function hue(): int
    {
        return crc32($this->name) % 360;
    }

    public function initials(): string
    {
        return Str::upper(Str::substr(preg_replace('/[^\p{L}\p{N} ]/u', '', $this->name), 0, 2));
    }

    public function inventoryValue(): int
    {
        return Qty::value(max(0, $this->qtyMilli()), (int) $this->cost);
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
