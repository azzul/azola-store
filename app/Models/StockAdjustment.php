<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockAdjustment extends Model
{
    public const REASONS = [
        'damaged' => 'Rusak',
        'lost' => 'Hilang',
        'expired' => 'Kedaluwarsa',
        'shrinkage' => 'Penyusutan',
        'found' => 'Barang ditemukan',
        'correction' => 'Koreksi pencatatan',
        'other' => 'Lainnya',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'value_total' => 'integer'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source');
    }
}
