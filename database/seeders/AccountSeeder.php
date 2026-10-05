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
        ['1301', 'inventory', 'Persediaan Barang', 'asset', 'debit'],
        ['2101', 'payable', 'Utang Usaha', 'liability', 'credit'],
        ['2102', 'tax_payable', 'Utang Pajak', 'liability', 'credit'],
        ['3101', 'equity', 'Modal Pemilik', 'equity', 'credit'],
        ['4101', 'sales', 'Penjualan', 'revenue', 'credit'],
        ['4102', 'shipping_income', 'Pendapatan Ongkir', 'revenue', 'credit'],
        ['5101', 'cogs', 'Harga Pokok Penjualan', 'cogs', 'debit'],
        ['5201', 'inventory_adjustment', 'Selisih Persediaan', 'expense', 'debit'],
    ];

    public function run(): void
    {
        foreach (self::CHART as [$code, $key, $name, $type, $normal]) {
            Account::updateOrCreate(
                ['key' => $key],
                ['code' => $code, 'name' => $name, 'type' => $type, 'normal_balance' => $normal],
            );
        }
    }
}
