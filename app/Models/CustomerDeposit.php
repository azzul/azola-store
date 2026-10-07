<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CustomerDeposit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'integer', 'used_total' => 'integer', 'refunded_total' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source');
    }

    public function balance(): int
    {
        return max(0, $this->amount - $this->used_total - $this->refunded_total);
    }
}
