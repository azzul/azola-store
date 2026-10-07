<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** Etalase = rak tampilan di toko ("Terlaris", "Promo", ...). Satu produk boleh ada di banyak etalase. */
class Etalase extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'is_visible'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ProductGroup::class, 'etalase_product_group');
    }

    public function url(): string
    {
        return route('shop.etalase', $this->slug);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'etalase';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
