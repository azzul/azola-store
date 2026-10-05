<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_published' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** @return array{count: int, average: float, bars: array<int, int>} */
    public static function summary(): array
    {
        $counts = static::published()->selectRaw('rating, COUNT(*) as n')->groupBy('rating')->pluck('n', 'rating');
        $count = (int) $counts->sum();
        $sum = $counts->map(fn ($n, $rating) => $n * $rating)->sum();

        return [
            'count' => $count,
            'average' => $count ? round($sum / $count, 1) : 0.0,
            'bars' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($r) => [$r => (int) ($counts[$r] ?? 0)])->all(),
        ];
    }
}
