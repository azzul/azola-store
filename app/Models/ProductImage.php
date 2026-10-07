<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = ['product_group_id', 'product_id', 'path', 'card_path', 'thumb_path', 'alt', 'sort_order'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** large | card | thumb. Foto lama (tanpa turunan) jatuh ke file aslinya. */
    public function url(string $size = 'large'): string
    {
        $path = match ($size) {
            'card' => $this->card_path ?: $this->path,
            'thumb' => $this->thumb_path ?: ($this->card_path ?: $this->path),
            default => $this->path,
        };

        return Storage::disk('public')->url($path);
    }

    protected static function booted(): void
    {
        static::deleted(function (ProductImage $image) {
            \App\Support\Images::delete($image->path, $image->card_path, $image->thumb_path);
        });
    }
}
