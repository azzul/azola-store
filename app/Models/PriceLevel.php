<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PriceLevel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }
}
