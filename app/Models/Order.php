<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    public const CHANNELS = ['web', 'pos_desktop', 'pos_android', 'admin'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'shipping_fee' => 'integer',
            'grand_total' => 'integer',
            'paid_total' => 'integer',
            'cogs_total' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function outstanding(): int
    {
        return max(0, $this->grand_total - $this->paid_total);
    }

    public function isWeb(): bool
    {
        return $this->channel === 'web';
    }
}
