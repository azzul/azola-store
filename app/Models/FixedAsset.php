<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FixedAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['acquired_on' => 'date', 'cost' => 'integer', 'salvage' => 'integer', 'depreciated' => 'integer'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(DepreciationRun::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source');
    }

    public function depreciable(): int
    {
        return max(0, $this->cost - $this->salvage);
    }

    public function monthly(): int
    {
        return $this->life_months > 0 ? intdiv($this->depreciable(), $this->life_months) : 0;
    }

    public function bookValue(): int
    {
        return $this->cost - $this->depreciated;
    }
}
