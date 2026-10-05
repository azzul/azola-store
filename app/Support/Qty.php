<?php

namespace App\Support;

/**
 * Hitung kuantitas dalam "milli" (1 unit = 1000 milli) agar semua operasi
 * stok memakai bilangan bulat dan bebas dari galat floating point.
 */
final class Qty
{
    public static function toMilli(string|int|float|null $value): int
    {
        return (int) round(((float) ($value ?? 0)) * 1000);
    }

    public static function fromMilli(int $milli): string
    {
        $sign = $milli < 0 ? '-' : '';
        $abs = abs($milli);

        return sprintf('%s%d.%03d', $sign, intdiv($abs, 1000), $abs % 1000);
    }

    /** Nilai rupiah dari kuantitas (milli) x harga satuan, dibulatkan ke rupiah. */
    public static function value(int $milli, int $unitPrice): int
    {
        return (int) round($milli * $unitPrice / 1000);
    }

    /** "12.000" => "12", "1.500" => "1,5" untuk tampilan. */
    public static function pretty(string|int|float|null $value): string
    {
        $milli = self::toMilli($value);
        $text = rtrim(rtrim(self::fromMilli($milli), '0'), '.');

        return str_replace('.', ',', $text === '' || $text === '-' ? '0' : $text);
    }
}
