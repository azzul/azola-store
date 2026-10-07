<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Qty;

/**
 * HPP rata-rata tertimbang untuk seluruh gudang. Setiap dokumen yang menambah atau mengurangi
 * nilai persediaan memanggil revalue() supaya akun Persediaan tetap sama dengan stok x HPP.
 */
class CostingService
{
    /**
     * @param  Product  $locked  produk yang sudah dikunci (lockForUpdate) SEBELUM stok diubah
     * @param  int  $deltaMilli  perubahan jumlah (+ masuk, - keluar), satuan dasar
     * @param  int  $deltaValue  perubahan nilai rupiah yang dicatat di jurnal (+ / -)
     * @return int sisa nilai yang harus dihapuskan bila stok menjadi 0 tetapi nilai masih ada (0 jika tidak ada)
     */
    public function revalue(Product $locked, int $deltaMilli, int $deltaValue): int
    {
        $beforeMilli = max(0, $locked->totalMilli());
        $beforeValue = Qty::value($beforeMilli, (int) $locked->cost);

        $afterMilli = $beforeMilli + $deltaMilli;
        $afterValue = $beforeValue + $deltaValue;

        if ($afterMilli > 0) {
            $locked->forceFill(['cost' => max(0, (int) round($afterValue * 1000 / $afterMilli))])->save();

            return 0;
        }

        // Stok habis: HPP terakhir dipertahankan. Nilai yang tersisa di buku harus dihapus agar akun persediaan nol.
        return max(0, $afterValue);
    }

    /** Baris jurnal untuk menghapus sisa nilai persediaan (lihat revalue). */
    public function residualLines(int $residual): array
    {
        return $residual > 0 ? [
            ['account' => 'inventory_adjustment', 'debit' => $residual, 'memo' => 'Sisa nilai persediaan dihapus'],
            ['account' => 'inventory', 'credit' => $residual, 'memo' => 'Sisa nilai persediaan dihapus'],
        ] : [];
    }

    /** Nomor dokumen: PREFIX-yymmdd-00001 (memakai id agar unik dan berurutan). */
    public static function number(string $prefix, \Carbon\CarbonInterface $date, int $id): string
    {
        return sprintf('%s-%s-%05d', $prefix, $date->format('ymd'), $id);
    }
}
