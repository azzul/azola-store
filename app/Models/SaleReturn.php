<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SaleReturn extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'subtotal' => 'integer', 'tax' => 'integer', 'total' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source');
    }
}
