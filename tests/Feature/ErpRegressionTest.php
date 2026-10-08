<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\FinanceService;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Services\ReconciliationService;
use App\Services\ReportService;
use App\Services\StockDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

/** Bug yang ditemukan uji acak (ErpFuzzTest); tiap kasus dikunci supaya tidak kembali. */
class ErpRegressionTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
    }

    private function assertBooksOk(): void
    {
        $bad = collect(app(ReconciliationService::class)->run()['checks'])->where('ok', false)->map(fn ($c) => $c['title'].' '.json_encode($c['rows']))->all();
        $this->assertSame([], $bad);
        $trial = app(ReportService::class)->trial('2000-01-01', '2100-01-01');
        $this->assertSame($trial['debit'], $trial['credit']);
        $this->assertTrue(app(ReportService::class)->balanceSheet('2100-01-01')['totals']['balanced']);
    }

    private function buy($product, int $qty, int $price, ?Supplier $supplier = null, string $method = 'cash'): \App\Models\Purchase
    {
        return app(PurchaseService::class)->create([
            'supplier_id' => $supplier?->id, 'date' => today()->toDateString(), 'payment_method' => $method,
            'items' => [['product_id' => $product->id, 'qty' => $qty, 'price' => $price]],
        ]);
    }

    public function test_balance_sheet_subtracts_accumulated_depreciation(): void
    {
        $finance = app(FinanceService::class);
        $finance->addAsset(['name' => 'Rak', 'cost' => 1200000, 'salvage' => 0, 'life_months' => 12, 'acquired_on' => today()->subMonths(2)->toDateString(), 'paid_via' => 'cash']);
        $finance->depreciate(now()->format('Y-m'));

        $sheet = app(ReportService::class)->balanceSheet('2100-01-01');
        $this->assertTrue($sheet['totals']['balanced']);
        $this->assertBooksOk();
    }

    public function test_cancelled_order_restores_stock_at_the_value_it_left(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 20000]);
        [$order] = app(OrderService::class)->create(['items' => [['product_id' => $product->id, 'qty' => 5]], 'payment_method' => 'cod', 'customer_name' => 'Budi', 'customer_phone' => '08123'], 'web');

        $this->buy($product, 10, 12000); // HPP rata-rata berubah selagi pesanan terbuka
        app(OrderService::class)->cancel($order->refresh());

        $this->assertBooksOk();
    }

    public function test_cancelling_expensive_invoice_after_cheap_stock_was_used_keeps_inventory_account_equal_to_stock(): void
    {
        $product = $this->makeProduct(['price' => 50000]);
        $first = $this->buy($product, 10, 10000);
        $this->buy($product, 10, 2000);
        app(OrderService::class)->create(['items' => [['product_id' => $product->id, 'qty' => 10]], 'payment_method' => 'cod', 'customer_name' => 'Budi', 'customer_phone' => '08123'], 'web');

        app(PurchaseService::class)->cancel($first);

        $this->assertBooksOk();
    }

    public function test_editing_an_invoice_keeps_the_value_adjustment_it_just_posted(): void
    {
        $purchases = app(PurchaseService::class);
        $product = $this->makeProduct(['price' => 50000]);
        $invoice = $this->buy($product, 10, 10000);
        app(OrderService::class)->create(['items' => [['product_id' => $product->id, 'qty' => 10]], 'payment_method' => 'cod', 'customer_name' => 'Budi', 'customer_phone' => '08123'], 'web');

        // Barang sudah habis terjual; harga faktur dikoreksi turun. Selisih nilainya harus tetap tercatat.
        $purchases->update($invoice, ['supplier_id' => null, 'date' => today()->toDateString(), 'payment_method' => 'cash', 'items' => [['product_id' => $product->id, 'qty' => 10, 'price' => 1000]]]);
        $this->assertBooksOk();

        $purchases->update($invoice->refresh(), ['supplier_id' => null, 'date' => today()->toDateString(), 'payment_method' => 'cash', 'items' => [['product_id' => $product->id, 'qty' => 10, 'price' => 3000]]]);
        $this->assertBooksOk();
    }

    public function test_return_larger_than_payable_leaves_a_collectable_supplier_receivable(): void
    {
        $purchases = app(PurchaseService::class);
        $supplier = Supplier::create(['name' => 'CV Maju', 'term_days' => 14]);
        $product = $this->makeProduct();
        $invoice = $this->buy($product, 10, 10000, $supplier, 'credit');
        $purchases->pay($supplier, 60000, 'cash', today()->toDateString());

        $return = $purchases->createReturn(['purchase_id' => $invoice->id, 'supplier_id' => $supplier->id, 'settlement' => 'payable', 'items' => [['product_id' => $product->id, 'qty' => 10, 'price' => 10000]]]);

        $this->assertSame(0, $supplier->payable());
        $this->assertSame(60000, $supplier->receivable());
        $this->assertSame(60000, Account::where('key', 'supplier_receivable')->first()->balance());

        $purchases->receive($supplier, 60000, 'cash');
        $this->assertSame(0, $supplier->receivable());
        $this->assertBooksOk();

        $this->assertNotNull($return->id);
    }

    public function test_transfer_shortfall_is_valued_at_current_cost(): void
    {
        $product = $this->stockedProduct('10', 6000);
        $docs = app(StockDocumentService::class);
        $second = Warehouse::create(['name' => 'Gudang B', 'code' => 'GB']);
        $transfer = $docs->createTransfer(['from_warehouse_id' => Warehouse::mainId(), 'to_warehouse_id' => $second->id, 'items' => [['product_id' => $product->id, 'qty' => 6]]]);

        $this->buy($product, 10, 14000); // HPP berubah saat barang di jalan
        $docs->receiveTransfer($transfer, [$product->id => 4]);

        $this->assertBooksOk();
    }
}
