<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduhan CSV untuk laporan (UTF-8 dengan BOM agar langsung rapi di Excel). */
final class Csv
{
    /** @param  iterable<array<int, scalar|null>>  $rows */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) ? self::safe($v) : $v, $row), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah injeksi rumus spreadsheet (sel yang diawali = + - @). */
    private static function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value) ? "'".$value : $value;
    }
}
