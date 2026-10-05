<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Jurnal bersifat permanen. Satu-satunya kolom yang boleh berubah adalah
 * reversed_by_id (penanda bahwa jurnal sudah dibalik). Koreksi = jurnal balik.
 */
class Journal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'total' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (Journal $journal) {
            $allowed = ['reversed_by_id', 'updated_at'];

            // Nomor jurnal diisi sekali, tepat setelah baris dibuat (nomor memakai id).
            if ($journal->getOriginal('number') === null) {
                $allowed[] = 'number';
            }

            $changed = array_diff(array_keys($journal->getDirty()), $allowed);

            if ($changed !== []) {
                throw new LogicException('Jurnal yang sudah diposting tidak boleh diubah. Buat jurnal balik.');
            }
        });

        static::deleting(fn () => throw new LogicException('Jurnal tidak boleh dihapus. Buat jurnal balik.'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_id !== null;
    }
}
