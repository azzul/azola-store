<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockOpnameItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function diff(): ?string
    {
        return $this->counted_qty === null ? null : \App\Support\Qty::fromMilli(\App\Support\Qty::toMilli($this->counted_qty) - \App\Support\Qty::toMilli($this->system_qty));
    }
}
