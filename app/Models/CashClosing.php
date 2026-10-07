<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CashClosing extends Model
{
    public const DENOMINATIONS = [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'denominations' => 'array'];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source');
    }
}
