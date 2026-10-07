<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mengolah satu foto unggahan menjadi tiga ukuran dengan GD (tanpa paket tambahan):
 *  - besar  : maks. 1400 px, rasio asli     -> halaman detail dan lightbox
 *  - kartu  : 640x640 dipotong dari tengah  -> kartu produk, supaya semua kartu rata
 *  - mini   : 160x160                       -> miniatur galeri
 * Kartu dan detail memakai foto sumber yang sama, jadi yang dilihat di kartu pasti ada di detail.
 */
final class Images
{
    public const LARGE = 1400;
    public const CARD = 640;
    public const THUMB = 160;

    /** @return array{path: string, card_path: string, thumb_path: string} */
    public static function store(UploadedFile|string $file, string $dir = 'products'): array
    {
        $bytes = $file instanceof UploadedFile ? file_get_contents($file->getRealPath()) : file_get_contents($file);
        $src = $bytes === false ? false : @imagecreatefromstring($bytes);

        if (! $src) {
            throw new RuntimeException('File gambar tidak bisa dibaca.');
        }

        if ($file instanceof UploadedFile && function_exists('exif_read_data') && in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            $src = self::orient($src, $file->getRealPath());
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
        $base = trim($dir, '/').'/'.Str::lower(Str::random(14));

        $large = self::fit($src, $w, $h, self::LARGE);
        $card = self::cover($src, $w, $h, self::CARD);
        $thumb = self::cover($src, $w, $h, self::THUMB);

        $paths = [
            'path' => "{$base}.{$ext}",
            'card_path' => "{$base}-card.{$ext}",
            'thumb_path' => "{$base}-th.{$ext}",
        ];

        self::write($large, $paths['path'], $ext);
        self::write($card, $paths['card_path'], $ext);
        self::write($thumb, $paths['thumb_path'], $ext);

        foreach ([$src, $large, $card, $thumb] as $img) {
            imagedestroy($img);
        }

        return $paths;
    }

    /**
     * Sampul artikel: besar (maks. 1400 px, rasio asli) dan kartu 16:9 (1000x560, dipotong dari tengah).
     *
     * @return array{path: string, card_path: string}
     */
    public static function storeWide(UploadedFile|string $file, string $dir = 'articles'): array
    {
        $bytes = $file instanceof UploadedFile ? file_get_contents($file->getRealPath()) : file_get_contents($file);
        $src = $bytes === false ? false : @imagecreatefromstring($bytes);
        if (! $src) {
            throw new RuntimeException('File gambar tidak bisa dibaca.');
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
        $base = trim($dir, '/').'/'.Str::lower(Str::random(14));

        $large = self::fit($src, $w, $h, self::LARGE);

        // potong 16:9 dari tengah
        $tw = 1000;
        $th = 560;
        $ratio = $tw / $th;
        $cw = $w / $h > $ratio ? (int) round($h * $ratio) : $w;
        $ch = $w / $h > $ratio ? $h : (int) round($w / $ratio);
        $card = self::canvas($tw, $th);
        imagecopyresampled($card, $src, 0, 0, (int) floor(($w - $cw) / 2), (int) floor(($h - $ch) / 2), $tw, $th, $cw, $ch);

        self::write($large, "{$base}.{$ext}", $ext);
        self::write($card, "{$base}-card.{$ext}", $ext);

        foreach ([$src, $large, $card] as $img) {
            imagedestroy($img);
        }

        return ['path' => "{$base}.{$ext}", 'card_path' => "{$base}-card.{$ext}"];
    }

    public static function delete(?string ...$paths): void
    {
        $paths = array_filter($paths);
        if ($paths) {
            Storage::disk('public')->delete($paths);
        }
    }

    private static function canvas(int $w, int $h)
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagealphablending($img, true);

        return $img;
    }

    private static function fit($src, int $w, int $h, int $max)
    {
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = self::canvas($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private static function cover($src, int $w, int $h, int $size)
    {
        $side = min($w, $h);
        $sx = (int) floor(($w - $side) / 2);
        $sy = (int) floor(($h - $side) / 2);
        $dst = self::canvas($size, $size);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);

        return $dst;
    }

    private static function write($img, string $path, string $ext): void
    {
        ob_start();
        if ($ext === 'webp') {
            imagewebp($img, null, 82);
        } else {
            // JPEG tidak punya alfa: ratakan ke latar putih.
            $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            imagejpeg($flat, null, 85);
            imagedestroy($flat);
        }
        $data = ob_get_clean();

        Storage::disk('public')->put($path, $data);
    }

    private static function orient($img, string $file)
    {
        $exif = @exif_read_data($file);
        $o = $exif['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;

        if ($angle) {
            $rot = imagerotate($img, $angle, 0);
            if ($rot) {
                imagedestroy($img);

                return $rot;
            }
        }

        return $img;
    }
}
