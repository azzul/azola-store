<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Purchase extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'due_date' => 'date', 'cancelled_at' => 'datetime',
            'subtotal' => 'integer', 'discount' => 'integer', 'grand_total' => 'integer',
            'paid_total' => 'integer', 'returned_total' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PayableAllocation::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outstanding(): int
    {
        return $this->status === 'posted' ? max(0, $this->grand_total - $this->paid_total) : 0;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOverdue(): bool
    {
        return $this->outstanding() > 0 && $this->due_date && $this->due_date->lt(today());
    }
}
