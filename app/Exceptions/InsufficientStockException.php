<?php

namespace App\Exceptions;

use App\Models\Product;
use App\Support\Qty;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly int $requestedMilli,
        public readonly int $availableMilli,
    ) {
        parent::__construct(sprintf(
            'Stok "%s" tidak cukup: tersedia %s, diminta %s.',
            $product->name,
            Qty::pretty(Qty::fromMilli($availableMilli)),
            Qty::pretty(Qty::fromMilli($requestedMilli)),
        ));
    }

    public function context(): array
    {
        return [
            'product_id' => $this->product->id,
            'sku' => $this->product->sku,
            'available' => Qty::fromMilli($this->availableMilli),
            'requested' => Qty::fromMilli($this->requestedMilli),
        ];
    }
}
