<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseReturn extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'cancelled_at' => 'datetime', 'total' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source');
    }

    /**
     * Bagian retur yang menjadi piutang supplier: seluruhnya bila diselesaikan sebagai piutang, atau sisa yang
     * melebihi hutang bila diselesaikan sebagai pemotong hutang.
     */
    public function receivableAmount(): int
    {
        if ($this->status === 'cancelled') {
            return 0;
        }

        return match ($this->settlement) {
            'receivable' => (int) $this->total,
            'payable' => max(0, (int) $this->total - (int) PayableAllocation::where('source_type', $this->getMorphClass())->where('source_id', $this->getKey())->sum('amount')),
            default => 0,
        };
    }
}
