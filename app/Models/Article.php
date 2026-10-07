<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'title', 'slug', 'topic', 'excerpt', 'body', 'cover_path', 'cover_card_path', 'cover_alt', 'author',
        'is_published', 'published_at', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('published_at', '<=', now());
    }

    public function url(): string
    {
        return route('articles.show', $this->slug);
    }

    public function coverUrl(string $size = 'large'): ?string
    {
        $path = $size === 'card' ? ($this->cover_card_path ?: $this->cover_path) : $this->cover_path;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->body)) / 200));
    }

    /** Markdown -> HTML aman: tag HTML mentah dibuang dan tautan berbahaya ditolak. */
    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function summary(): string
    {
        return $this->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($this->html()))), 160);
    }

    public function hue(): int
    {
        return crc32($this->title) % 360;
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'artikel';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
