<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductPrice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }
}
