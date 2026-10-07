<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(\App\Support\StoreSettings::CACHE_KEY));
        static::deleted(fn () => Cache::forget(\App\Support\StoreSettings::CACHE_KEY));
    }
}
