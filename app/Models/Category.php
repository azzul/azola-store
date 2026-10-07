<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ProductGroup::class);
    }

    public function url(): string
    {
        return route('shop.category', $this->slug);
    }
}
