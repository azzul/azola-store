<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Supplier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** Sisa utang ke supplier ini (faktur aktif). */
    public function payable(): int
    {
        return (int) $this->purchases()->where('status', 'posted')->selectRaw('COALESCE(SUM(grand_total - paid_total), 0) as v')->value('v');
    }

    /** Saldo piutang supplier (mereka berutang ke kita). */
    public function receivable(): int
    {
        $returns = (int) PurchaseReturn::where('supplier_id', $this->id)->where('status', 'posted')->where('settlement', 'receivable')->sum('total');
        $received = (int) SupplierPayment::where('supplier_id', $this->id)->where('status', 'posted')->where('direction', 'receive')->sum('amount');

        return max(0, $returns - $received);
    }
}
