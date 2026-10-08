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
     * @return int selisih nilai buku yang tidak tertampung HPP (0 jika tidak ada); lihat residualLines()
     */
    public function revalue(Product $locked, int $deltaMilli, int $deltaValue): int
    {
        $beforeMilli = max(0, $locked->totalMilli());
        $beforeValue = Qty::value($beforeMilli, (int) $locked->cost);

        $afterMilli = $beforeMilli + $deltaMilli;
        $afterValue = $beforeValue + $deltaValue;

        if ($afterMilli > 0 && $afterValue >= 0) {
            $locked->forceFill(['cost' => (int) round($afterValue * 1000 / $afterMilli)])->save();

            return 0;
        }

        // Stok habis, atau nilainya jadi minus (mis. faktur mahal dibatalkan setelah barang murah terjual):
        // HPP tidak bisa menampung nilai buku itu, jadi selisihnya dibukukan sebagai penyesuaian agar
        // akun Persediaan tetap sama dengan stok x HPP. Positif = hapus sisa nilai, negatif = kembalikan kekurangan.
        if ($afterMilli > 0) {
            $locked->forceFill(['cost' => 0])->save();
        }

        return $afterValue;
    }

    /** Baris jurnal untuk menutup selisih nilai persediaan (lihat revalue). */
    public function residualLines(int $residual): array
    {
        if ($residual === 0) {
            return [];
        }

        $amount = abs($residual);

        return $residual > 0 ? [
            ['account' => 'inventory_adjustment', 'debit' => $amount, 'memo' => 'Sisa nilai persediaan dihapus'],
            ['account' => 'inventory', 'credit' => $amount, 'memo' => 'Sisa nilai persediaan dihapus'],
        ] : [
            ['account' => 'inventory', 'debit' => $amount, 'memo' => 'Koreksi nilai persediaan (HPP lebih rendah dari nilai faktur)'],
            ['account' => 'inventory_adjustment', 'credit' => $amount, 'memo' => 'Koreksi nilai persediaan (HPP lebih rendah dari nilai faktur)'],
        ];
    }

    /** Nomor dokumen: PREFIX-yymmdd-00001 (memakai id agar unik dan berurutan). */
    public static function number(string $prefix, \Carbon\CarbonInterface $date, int $id): string
    {
        return sprintf('%s-%s-%05d', $prefix, $date->format('ymd'), $id);
    }
}
