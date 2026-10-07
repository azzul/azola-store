<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Warehouse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_main' => 'boolean', 'is_active' => 'boolean'];
    }

    public static function main(): self
    {
        return static::where('is_main', true)->orderBy('id')->first()
            ?? static::create(['code' => 'TOKO', 'name' => 'Toko (gudang jual)', 'is_main' => true]);
    }

    public static function mainId(): int
    {
        return static::main()->id;
    }

    public function label(): string
    {
        return $this->name;
    }
}
