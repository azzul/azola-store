<?php

namespace App\Support;

final class Rupiah
{
    public static function format(int|float|string|null $amount): string
    {
        $amount = (int) round((float) ($amount ?? 0));
        $sign = $amount < 0 ? '-' : '';

        return $sign.'Rp'.number_format(abs($amount), 0, ',', '.');
    }
}
