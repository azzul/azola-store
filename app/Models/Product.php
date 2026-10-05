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
        'category_id', 'sku', 'barcode', 'name', 'slug', 'description', 'unit',
        'price', 'min_stock', 'image_path', 'is_active', 'is_online', 'is_featured',
        'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
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
        return route('shop.product', $this->slug);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
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
