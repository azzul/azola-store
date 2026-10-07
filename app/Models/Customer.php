<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'credit_limit' => 'integer'];
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(CustomerDeposit::class);
    }

    public function receivable(): int
    {
        return (int) $this->orders()->where('status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(grand_total - paid_total - returned_total), 0) as v')->value('v');
    }

    public function depositBalance(): int
    {
        return (int) $this->deposits()->selectRaw('COALESCE(SUM(amount - used_total - refunded_total), 0) as v')->value('v');
    }

    /** Cari atau buat dari data pesanan (telepon jadi kunci). Nama umum tidak dibuatkan master. */
    public static function fromContact(?string $name, ?string $phone, ?string $email = null, ?string $address = null): ?self
    {
        $phone = preg_replace('/\D/', '', (string) $phone);
        if ($phone === '') {
            return null;
        }

        return static::firstOrCreate(['phone' => $phone], [
            'name' => $name ?: 'Pelanggan '.$phone, 'email' => $email, 'address' => $address,
        ]);
    }
}
