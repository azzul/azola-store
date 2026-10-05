<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Buku besar stok. Hanya boleh ditambah: koreksi dilakukan dengan mutasi baru.
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Mutasi stok tidak boleh diubah. Buat mutasi koreksi.'));
        static::deleting(fn () => throw new LogicException('Mutasi stok tidak boleh dihapus. Buat mutasi koreksi.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
