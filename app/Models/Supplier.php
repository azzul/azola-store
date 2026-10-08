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
        return max(0, $this->receivableRaw());
    }

    /** Piutang sebelum dibatasi 0 (negatif = dana yang diterima melebihi retur yang masih berlaku). */
    public function receivableRaw(): int
    {
        $returns = PurchaseReturn::where('supplier_id', $this->id)->where('status', 'posted')->whereIn('settlement', ['receivable', 'payable'])->get()
            ->sum(fn (PurchaseReturn $r) => $r->receivableAmount());
        $received = (int) SupplierPayment::where('supplier_id', $this->id)->where('status', 'posted')->where('direction', 'receive')->sum('amount');

        return (int) $returns - $received;
    }
}
