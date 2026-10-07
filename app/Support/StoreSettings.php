<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan toko yang disimpan di database dan bisa diubah dari admin (menu Pengaturan toko).
 * Nilai di database menimpa config/store.php (yang berasal dari .env). Kolom dikosongkan = kembali ke nilai bawaan.
 */
final class StoreSettings
{
    public const CACHE_KEY = 'store.settings';

    /**
     * key => [path config, label, kelompok, jenis]. Jenis: text, textarea, color, number, year, url, email, username, phone, hours.
     */
    public const FIELDS = [
        'name' => ['store.name', 'Nama toko', 'Identitas', 'text'],
        'tagline' => ['store.tagline', 'Slogan singkat', 'Identitas', 'text'],
        'headline' => ['store.about.headline', 'Judul halaman Tentang kami', 'Identitas', 'text'],
        'since' => ['store.about.since', 'Tahun berdiri', 'Identitas', 'year'],
        'brand' => ['store.theme.brand', 'Warna utama', 'Tampilan', 'color'],
        'accent' => ['store.theme.accent', 'Warna aksen', 'Tampilan', 'color'],
        'whatsapp' => ['store.whatsapp', 'WhatsApp (angka, awali 62)', 'Kontak', 'phone'],
        'email' => ['store.email', 'Email', 'Kontak', 'email'],
        'address' => ['store.address', 'Alamat toko', 'Kontak', 'textarea'],
        'map_url' => ['store.map_url', 'Tautan Google Maps', 'Kontak', 'url'],
        'hours' => ['store.hours', 'Jam buka (satu per baris: Hari | Jam)', 'Kontak', 'hours'],
        'instagram' => ['store.instagram', 'Instagram (username)', 'Media sosial', 'username'],
        'tiktok' => ['store.tiktok', 'TikTok (username)', 'Media sosial', 'username'],
        'facebook' => ['store.facebook', 'Facebook (tautan halaman)', 'Media sosial', 'url'],
        'youtube' => ['store.youtube', 'YouTube (tautan kanal)', 'Media sosial', 'url'],
        'bank_name' => ['store.bank.name', 'Nama bank', 'Rekening transfer', 'text'],
        'bank_account' => ['store.bank.account', 'Nomor rekening', 'Rekening transfer', 'text'],
        'bank_holder' => ['store.bank.holder', 'Atas nama', 'Rekening transfer', 'text'],
        'shipping_flat' => ['store.shipping.flat', 'Ongkos kirim tetap (Rp)', 'Pengiriman', 'number'],
        'shipping_free_over' => ['store.shipping.free_over', 'Gratis ongkir mulai belanja (Rp, 0 = tidak ada)', 'Pengiriman', 'number'],
    ];

    /** Semua nilai tersimpan: key => string. */
    public static function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
    }

    /** Terapkan ke config() supaya seluruh tampilan memakainya. Aman dipanggil sebelum tabel ada. */
    public static function apply(): void
    {
        try {
            $stored = self::stored();
        } catch (\Throwable) {
            return;
        }

        foreach (self::FIELDS as $key => [$path, , , $type]) {
            $value = $stored[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            config([$path => self::cast($type, $value)]);
        }

        if (! empty($stored['logo'])) {
            config(['store.logo' => $stored['logo']]);
        }
    }

    public static function cast(string $type, string $value): mixed
    {
        return match ($type) {
            'number' => (int) $value,
            'hours' => self::parseHours($value),
            default => $value,
        };
    }

    /** @return array<int, array{0: string, 1: string}> */
    public static function parseHours(string $text): array
    {
        return collect(preg_split('/\R/', $text))->map(fn ($l) => trim($l))->filter()->map(function ($line) {
            [$day, $time] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');

            return [$day, $time];
        })->values()->all();
    }

    public static function hoursToText(array $hours): string
    {
        return collect($hours)->map(fn ($h) => $h[0].' | '.$h[1])->implode("\n");
    }
}
