<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Customer;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FinanceService;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Services\SalesDocumentService;
use App\Services\StockDocumentService;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk modul ERP: supplier, customer, gudang, level harga, konversi satuan, pembelian kredit + pembayaran,
 * retur, alih gudang, penjualan, DP, aset, dan biaya. Semua lewat service resmi supaya jurnal & stok ikut terbentuk.
 * Hapus/ganti sebelum dipakai klien. Aman dijalankan ulang: berhenti bila sudah ada supplier.
 */
class DemoErpSeeder extends Seeder
{
    public function run(): void
    {
        if (Supplier::exists() || ! Product::exists()) {
            return;
        }

        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $products = Product::orderBy('id')->take(4)->get();
        if ($products->count() < 2) {
            return;
        }

        $grosir = PriceLevel::firstOrCreate(['name' => 'Grosir'], ['sort_order' => 1]);
        $gudang = Warehouse::firstOrCreate(['code' => 'GD1'], ['name' => 'Gudang belakang']);

        $first = $products[0];
        $first->unitConversions()->firstOrCreate(['unit' => 'dus'], ['factor' => 12]);
        $second = $products[1];
        $second->levelPrices()->updateOrCreate(['price_level_id' => $grosir->id], ['price' => (int) round($second->price * 0.9)]);

        $supplier = Supplier::create(['name' => 'CV Sumber Pangan', 'phone' => '024-7600123', 'address' => 'Semarang', 'term_days' => 14]);
        Supplier::create(['name' => 'UD Maju Jaya', 'phone' => '0812-0000-111', 'term_days' => 0]);
        $budi = Customer::create(['name' => 'Toko Budi (reseller)', 'phone' => '081200000001', 'price_level_id' => $grosir->id]);
        Customer::create(['name' => 'Ibu Sari', 'phone' => '081200000002']);

        $purchases = app(PurchaseService::class);
        $buy = $purchases->create([
            'supplier_id' => $supplier->id, 'payment_method' => 'credit', 'date' => today()->subDays(12)->toDateString(),
            'supplier_invoice' => 'INV-SP-0042',
            'items' => $products->map(fn ($p) => ['product_id' => $p->id, 'qty' => 20, 'price' => max(1, (int) round($p->cost ?: $p->price * 0.7))])->all(),
        ], $admin);
        $purchases->pay($supplier, (int) round($buy->grand_total / 2), 'bank', today()->subDays(5)->toDateString(), null, 'Cicilan pertama', $admin);
        $purchases->createReturn([
            'purchase_id' => $buy->id, 'supplier_id' => $supplier->id, 'date' => today()->subDays(4)->toDateString(), 'settlement' => 'payable', 'reason' => 'Kemasan rusak',
            'items' => [['product_id' => $first->id, 'qty' => 2, 'price' => max(1, (int) round($first->cost))]],
        ], $admin);

        $docs = app(StockDocumentService::class);
        $docs->createTransfer(['from_warehouse_id' => Warehouse::mainId(), 'to_warehouse_id' => $gudang->id, 'note' => 'Stok cadangan', 'items' => [['product_id' => $second->id, 'qty' => 5]]], $admin);
        $docs->createAdjustment(['reason' => 'damaged', 'note' => 'Jatuh saat menata rak', 'items' => [['product_id' => $first->id, 'qty_change' => -1]]], $admin);

        $orders = app(OrderService::class);
        [$sale] = $orders->create(['items' => [['product_id' => $first->id, 'qty' => 3], ['product_id' => $second->id, 'qty' => 2]], 'payment_method' => 'transfer', 'paid_total' => 20000, 'buyer_id' => $budi->id, 'due_date' => today()->addDays(7)->toDateString()], 'admin', $admin);
        $orders->create(['items' => [['product_id' => $first->id, 'qty' => 1]], 'payment_method' => 'qris'], 'pos_desktop', $admin);
        $orders->create(['items' => [['product_id' => $second->id, 'qty' => 1]], 'payment_method' => 'cash'], 'pos_android', $admin);

        $sales = app(SalesDocumentService::class);
        $sales->createReturn($sale, ['items' => [['order_item_id' => $sale->items->first()->id, 'qty' => 1]], 'refund_method' => 'cash', 'reason' => 'Salah ukuran'], $admin);
        $sales->createDeposit(['customer_id' => $budi->id, 'amount' => 100000, 'method' => 'transfer', 'note' => 'DP pesanan bulan depan'], $admin);

        $finance = app(FinanceService::class);
        $finance->addAsset(['name' => 'Rak display', 'cost' => 2400000, 'salvage' => 0, 'life_months' => 24, 'acquired_on' => today()->subMonths(3)->toDateString(), 'paid_via' => 'equity'], $admin);
        $finance->depreciate(now()->format('Y-m'), $admin);
        if ($expense = Account::where('key', 'utilities')->first()) {
            $finance->cashEntry(['kind' => 'expense', 'account_id' => $expense->id, 'amount' => 350000, 'description' => 'Listrik bulan ini', 'date' => today()->subDays(3)->toDateString()], $admin);
        }
    }
}
