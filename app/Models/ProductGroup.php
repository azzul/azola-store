<?php

namespace App\Models;

use App\Support\Qty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Produk di etalase toko. Punya banyak variasi (Product), tiap variasi punya SKU, harga, dan stok sendiri.
 * Produk tanpa variasi tetap lewat sini: grup 1:1 dibuat otomatis (auto = true).
 */
class ProductGroup extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'brand', 'summary', 'description', 'highlights', 'specs', 'option_names',
        'auto', 'is_active', 'is_online', 'is_featured', 'meta_title', 'meta_description', 'price_min', 'price_max',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'specs' => 'array',
            'option_names' => 'array',
            'auto' => 'boolean',
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'is_featured' => 'boolean',
            'price_min' => 'integer',
            'price_max' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'group_id')->orderBy('sort_order')->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function etalases(): BelongsToMany
    {
        return $this->belongsToMany(Etalase::class, 'etalase_product_group');
    }

    /** Tampil di toko: grup aktif dan punya minimal satu variasi yang dijual online. */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_online', true)
            ->whereHas('variants', fn ($v) => $v->online());
    }

    public function url(): string
    {
        return route('shop.product', $this->slug);
    }

    /** Variasi yang dijual online (variants harus sudah dimuat atau akan dimuat sekali). */
    public function sellable(): Collection
    {
        return $this->loadMissing('variants')->variants->filter(fn (Product $p) => $p->is_active && $p->is_online)->values();
    }

    public function isSingle(): bool
    {
        return $this->sellable()->count() === 1;
    }

    public function solo(): ?Product
    {
        return $this->isSingle() ? $this->sellable()->first() : null;
    }

    public function getPriceAttribute(): int
    {
        return (int) $this->price_min;
    }

    public function hasRange(): bool
    {
        return $this->price_max > $this->price_min;
    }

    public function unit(): string
    {
        $units = $this->sellable()->pluck('unit')->unique();

        return $units->count() === 1 ? (string) $units->first() : '';
    }

    public function isInStock(): bool
    {
        return $this->sellable()->contains(fn (Product $p) => $p->isInStock());
    }

    public function stockState(): string
    {
        if ($one = $this->solo()) {
            return $one->stockState();
        }

        $live = $this->sellable()->filter(fn (Product $p) => $p->isInStock());
        if ($live->isEmpty()) {
            return 'out';
        }

        return $live->every(fn (Product $p) => $p->stockState() === 'low') ? 'low' : 'ok';
    }

    public function publicStockLabel(): string
    {
        if ($one = $this->solo()) {
            return $one->publicStockLabel();
        }

        return match ($this->stockState()) {
            'out' => 'Stok habis',
            'low' => 'Stok terbatas',
            default => 'Stok tersedia',
        };
    }

    /** Total stok semua variasi (hanya untuk admin; di toko publik jangan ditampilkan persis). */
    public function totalStockMilli(): int
    {
        return $this->sellable()->sum(fn (Product $p) => max(0, $p->qtyMilli()));
    }

    public function cover(): ?ProductImage
    {
        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $images->first(fn ($i) => $i->product_id === null) ?? $images->first();
    }

    public function imageUrl(string $size = 'card'): ?string
    {
        return $this->cover()?->url($size);
    }

    public function hue(): int
    {
        return crc32($this->name) % 360;
    }

    public function initials(): string
    {
        return Str::upper(Str::substr(preg_replace('/[^\p{L}\p{N} ]/u', '', $this->name), 0, 2));
    }

    /** Hitung ulang rentang harga dari variasi yang dijual. Dipanggil otomatis saat variasi disimpan. */
    public function refreshPrices(): void
    {
        $prices = $this->variants()->where('is_active', true)->where('is_online', true)->pluck('price');
        if ($prices->isEmpty()) {
            $prices = $this->variants()->pluck('price');
        }

        $min = (int) ($prices->min() ?? 0);
        $max = (int) ($prices->max() ?? 0);

        if ($min !== (int) $this->price_min || $max !== (int) $this->price_max) {
            $this->forceFill(['price_min' => $min, 'price_max' => $max])->saveQuietly();
        }
    }

    /** Sumbu pilihan di halaman detail: nama opsi (Ukuran, Warna) dan nilai-nilainya berurutan. */
    public function axes(): array
    {
        $names = array_values(array_filter((array) $this->option_names));
        $variants = $this->sellable();

        if (! $names) {
            $values = $variants->pluck('variant_name')->filter()->unique()->values()->all();

            return count($values) > 1 || ($values && ! $this->isSingle()) ? [['name' => 'Pilihan', 'values' => $values, 'plain' => true]] : [];
        }

        return collect($names)->map(fn ($n) => [
            'name' => $n,
            'values' => $variants->map(fn (Product $p) => $p->options[$n] ?? null)->filter()->unique()->values()->all(),
            'plain' => false,
        ])->filter(fn ($a) => $a['values'])->values()->all();
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
