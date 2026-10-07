<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /** Bagan akun dasar toko. Kolom "key" dipakai kode; "code" boleh diubah sesuai kebutuhan klien. */
    public const CHART = [
        ['1101', 'cash', 'Kas', 'asset', 'debit'],
        ['1102', 'bank', 'Bank & QRIS', 'asset', 'debit'],
        ['1201', 'receivable', 'Piutang Usaha', 'asset', 'debit'],
        ['1202', 'supplier_receivable', 'Piutang Supplier', 'asset', 'debit'],
        ['1301', 'inventory', 'Persediaan Barang', 'asset', 'debit'],
        ['1401', 'fixed_assets', 'Aset Tetap', 'asset', 'debit'],
        ['1402', 'accumulated_depreciation', 'Akumulasi Penyusutan', 'asset', 'credit'],
        ['2101', 'payable', 'Utang Usaha', 'liability', 'credit'],
        ['2102', 'tax_payable', 'Utang Pajak', 'liability', 'credit'],
        ['2103', 'customer_deposit', 'Uang Muka Pelanggan (DP)', 'liability', 'credit'],
        ['3101', 'equity', 'Modal Pemilik', 'equity', 'credit'],
        ['4101', 'sales', 'Penjualan', 'revenue', 'credit'],
        ['4102', 'shipping_income', 'Pendapatan Ongkir', 'revenue', 'credit'],
        ['4201', 'sales_return', 'Retur Penjualan', 'revenue', 'debit'],
        ['4901', 'other_income', 'Pendapatan Lain-lain', 'revenue', 'credit'],
        ['5101', 'cogs', 'Harga Pokok Penjualan', 'cogs', 'debit'],
        ['5201', 'inventory_adjustment', 'Selisih Persediaan', 'expense', 'debit'],
        ['5202', 'inventory_shrinkage', 'Kerusakan & Penyusutan Stok', 'expense', 'debit'],
        ['5203', 'cash_over_short', 'Selisih Kas', 'expense', 'debit'],
        ['5301', 'depreciation_expense', 'Beban Penyusutan', 'expense', 'debit'],
        ['5401', 'operating_expense', 'Beban Operasional', 'expense', 'debit'],
        ['5402', null, 'Beban Gaji', 'expense', 'debit'],
        ['5403', null, 'Beban Sewa', 'expense', 'debit'],
        ['5404', null, 'Beban Listrik, Air & Internet', 'expense', 'debit'],
        ['5405', null, 'Beban Transportasi & Pengiriman', 'expense', 'debit'],
        ['5406', null, 'Beban Pemasaran', 'expense', 'debit'],
    ];

    /**
     * Aman dijalankan berulang: akun sistem (punya key) hanya dibuat bila belum ada, jadi nama dan kode
     * yang sudah diubah admin tidak tertimpa. Bila kode bentrok dengan akun lain, dicarikan kode kosong.
     */
    public function run(): void
    {
        foreach (self::CHART as [$code, $key, $name, $type, $normal]) {
            $exists = $key !== null
                ? Account::where('key', $key)->exists()
                : Account::where('name', $name)->exists();

            if ($exists) {
                continue;
            }

            while (Account::where('code', $code)->exists()) {
                $code = (string) ((int) $code + 1);
            }

            Account::create(['code' => $code, 'key' => $key, 'name' => $name, 'type' => $type, 'normal_balance' => $normal]);
        }
    }
}
