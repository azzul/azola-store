<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class UnitConversion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['factor' => 'decimal:3', 'price' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Harga jual per satuan besar: harga khusus bila diisi, kalau tidak harga dasar x faktor. */
    public function sellPrice(): int
    {
        return $this->price !== null ? (int) $this->price : (int) round($this->product->price * (float) $this->factor);
    }
}
