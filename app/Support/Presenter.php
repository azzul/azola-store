<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Journal;
use App\Models\Order;
use App\Models\Product;

/**
 * Bentuk JSON yang SAMA untuk web, Azola Pos desktop, dan Android.
 * Uang = bilangan bulat rupiah. Stok/qty = string desimal 3 digit ("12.500") agar tidak ada galat float.
 */
final class Presenter
{
    public static function product(Product $product, bool $withCost = false): array
    {
        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'unit' => $product->unit,
            'price' => (int) $product->price,
            'stock' => Qty::fromMilli($product->qtyMilli()),
            'min_stock' => Qty::fromMilli($product->minMilli()),
            'stock_state' => $product->stockState(),
            'is_active' => (bool) $product->is_active,
            'is_online' => (bool) $product->is_online,
            'image_url' => $product->imageUrl(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];

        if ($withCost) {
            $data['cost'] = (int) $product->cost;
        }

        return $data;
    }

    public static function category(Category $category): array
    {
        return ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug];
    }

    public static function order(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'uuid' => $order->uuid,
            'number' => $order->number,
            'channel' => $order->channel,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
                'address' => $order->customer_address,
            ],
            'delivery_method' => $order->delivery_method,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'tax_total' => $order->tax_total,
            'shipping_fee' => $order->shipping_fee,
            'grand_total' => $order->grand_total,
            'paid_total' => $order->paid_total,
            'outstanding' => $order->outstanding(),
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'sku' => $item->sku,
                'name' => $item->name,
                'qty' => Qty::fromMilli(Qty::toMilli($item->qty)),
                'price' => $item->price,
                'discount' => $item->discount,
                'line_total' => $item->line_total,
            ])->all(),
        ];
    }

    public static function journal(Journal $journal): array
    {
        $journal->loadMissing('lines.account');

        return [
            'id' => $journal->id,
            'number' => $journal->number,
            'date' => $journal->date?->toDateString(),
            'type' => $journal->type,
            'description' => $journal->description,
            'total' => $journal->total,
            'reversed' => $journal->isReversed(),
            'reversal_of_id' => $journal->reversal_of_id,
            'lines' => $journal->lines->map(fn ($line) => [
                'account_code' => $line->account->code,
                'account' => $line->account->name,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'memo' => $line->memo,
            ])->all(),
        ];
    }
}
