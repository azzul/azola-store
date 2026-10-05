<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Channel penjualan yang dicatat untuk perangkat ini. */
    public function channel(): string
    {
        return $this->device_type === 'android' ? 'pos_android' : 'pos_desktop';
    }

    /**
     * Buat token baru. Teks token hanya dikembalikan sekali di sini;
     * database hanya menyimpan hash SHA-256.
     *
     * @return array{0: ApiToken, 1: string}
     */
    public static function issue(User $user, string $name, string $deviceType): array
    {
        $plain = 'azp_'.Str::random(48);

        $token = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'device_type' => $deviceType,
            'token_hash' => hash('sha256', $plain),
        ]);

        return [$token, $plain];
    }

    public static function findByPlain(string $plain): ?self
    {
        return static::with('user')
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->first();
    }
}
