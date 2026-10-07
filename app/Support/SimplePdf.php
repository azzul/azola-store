<?php

namespace App\Support;

/**
 * Pembuat PDF sederhana tanpa paket tambahan: teks Helvetica (reguler dan tebal), kotak, dan garis, banyak halaman.
 * Cukup untuk dokumen tabel seperti pricelist. Koordinat dari pojok kiri atas, satuan poin (A4 = 595 x 842).
 * Teks dikonversi ke WinAnsi (huruf Latin dan tanda baca Indonesia aman); karakter di luar itu jadi "?".
 */
final class SimplePdf
{
    public const W = 595.28;
    public const H = 841.89;

    /** Lebar huruf Helvetica per 1000 em untuk karakter ASCII 32..126. */
    private const REGULAR = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

    private const BOLD = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    /** @var array<int, string[]> operasi gambar per halaman */
    private array $pages = [];

    private int $current = -1;

    public function addPage(): int
    {
        $this->pages[] = [];

        return $this->current = count($this->pages) - 1;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function setPage(int $index): void
    {
        $this->current = $index;
    }

    /** @param  array{0:int,1:int,2:int}  $rgb */
    public function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->op(sprintf('%s rg %.2f %.2f %.2f %.2f re f', $this->color($rgb), $x, self::H - $y - $h, $w, $h));
    }

    /** @param  array{0:int,1:int,2:int}  $rgb */
    public function line(float $x1, float $y, float $x2, array $rgb, float $width = 0.5): void
    {
        $this->op(sprintf('%s RG %.2f w %.2f %.2f m %.2f %.2f l S', $this->color($rgb), $width, $x1, self::H - $y, $x2, self::H - $y));
    }

    /** @param  array{0:int,1:int,2:int}  $rgb  y = garis dasar teks */
    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, array $rgb = [0, 0, 0], string $align = 'left'): void
    {
        if ($text === '') {
            return;
        }

        if ($align === 'right') {
            $x -= $this->width($text, $size, $bold);
        } elseif ($align === 'center') {
            $x -= $this->width($text, $size, $bold) / 2;
        }

        $this->op(sprintf('BT %s rg /%s %.2f Tf %.2f %.2f Td (%s) Tj ET', $this->color($rgb), $bold ? 'F2' : 'F1', $size, $x, self::H - $y, $this->escape($text)));
    }

    public function width(string $text, float $size, bool $bold = false): float
    {
        $table = $bold ? self::BOLD : self::REGULAR;
        $sum = 0;
        foreach (str_split($this->encode($text)) as $ch) {
            $code = ord($ch);
            $sum += ($code >= 32 && $code <= 126) ? $table[$code - 32] : 556;
        }

        return $sum * $size / 1000;
    }

    /** Potong teks dengan "..." supaya muat di lebar tertentu. */
    public function fit(string $text, float $maxWidth, float $size, bool $bold = false): string
    {
        if ($this->width($text, $size, $bold) <= $maxWidth) {
            return $text;
        }

        while ($text !== '' && $this->width($text.'...', $size, $bold) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'...';
    }

    public function output(string $title = ''): string
    {
        $objects = [];
        $add = function (string $body) use (&$objects): int {
            $objects[] = $body;

            return count($objects);
        };

        $catalog = $add('');
        $pagesRoot = $add('');
        $f1 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $f2 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

        $kids = [];
        foreach ($this->pages as $ops) {
            $stream = implode("\n", $ops);
            $filter = '';
            if (function_exists('gzcompress')) {
                $stream = gzcompress($stream, 6);
                $filter = ' /Filter /FlateDecode';
            }
            $content = $add('<< /Length '.strlen($stream).$filter." >>\nstream\n".$stream."\nendstream");
            $kids[] = $add(sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>', $pagesRoot, self::W, self::H, $f1, $f2, $content));
        }

        $objects[$catalog - 1] = "<< /Type /Catalog /Pages {$pagesRoot} 0 R >>";
        $objects[$pagesRoot - 1] = '<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ', array_map(fn ($k) => "{$k} 0 R", $kids)).'] >>';
        $info = $add('<< /Title ('.$this->escape($title).') /Producer (Azola Store) /CreationDate (D:'.date('YmdHis').') >>');

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($out);
            $out .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($out);
        $out .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        $out .= 'trailer << /Size '.(count($objects) + 1)." /Root {$catalog} 0 R /Info {$info} 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $out;
    }

    private function op(string $op): void
    {
        if ($this->current < 0) {
            $this->addPage();
        }
        $this->pages[$this->current][] = $op;
    }

    private function color(array $rgb): string
    {
        return sprintf('%.3f %.3f %.3f', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    }

    private function encode(string $text): string
    {
        $text = strtr($text, ["\u{2013}" => '-', "\u{2014}" => '-', "\u{2018}" => "'", "\u{2019}" => "'", "\u{201C}" => '"', "\u{201D}" => '"', "\u{2026}" => '...']);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '?', $text) : $converted;
    }

    private function escape(string $text): string
    {
        return strtr($this->encode($text), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }
}
