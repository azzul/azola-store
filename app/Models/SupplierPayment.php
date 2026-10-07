<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SupplierPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'cancelled_at' => 'datetime', 'amount' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source');
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PayableAllocation::class, 'source');
    }
}
