<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dipancarkan setiap stok berubah (setelah transaksi commit).
 * Saat ini klien membaca perubahan lewat feed /sync/stock. Bila nanti dipasang
 * Laravel Reverb, cukup tambahkan broadcast pada event ini tanpa mengubah service.
 */
class StockChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $productId,
        public readonly string $qtyAfter,
        public readonly int $movementId,
    ) {}
}
