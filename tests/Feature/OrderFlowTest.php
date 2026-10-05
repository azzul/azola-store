<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\UnbalancedJournalException;
use App\Models\Account;
use App\Models\Journal;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\JournalService;
use App\Services\OrderService;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
    }

    public function test_stock_in_updates_stock_average_cost_and_journal(): void
    {
        $product = $this->stockedProduct('10', 6000);
        app(InventoryService::class)->receive($product, '10', 8000, 'cash');

        $product->refresh();
        $this->assertSame(20000, $product->qtyMilli());
        $this->assertSame(7000, $product->cost); // (10*6000 + 10*8000) / 20
        $this->assertSame(2, StockMovement::where('product_id', $product->id)->count());

        // 60.000 (modal) + 80.000 (kas) masuk ke persediaan
        $this->assertSame(140000, Account::netDebitByKey('inventory'));
        $this->assertSame(-80000, Account::netDebitByKey('cash'));
        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_sale_reduces_stock_and_posts_balanced_journal(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);

        [$order, $created] = app(OrderService::class)->create([
            'items' => [['product_id' => $product->id, 'qty' => 3]],
            'payment_method' => 'cash',
        ], 'pos_desktop', $this->makeUser('cashier'));

        $this->assertTrue($created);
        $this->assertSame(30000, $order->grand_total);
        $this->assertSame(30000, $order->paid_total);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(18000, $order->cogs_total);
        $this->assertSame(7000, $product->fresh()->qtyMilli());

        $journal = $order->journals()->where('type', 'sale')->with('lines.account')->firstOrFail();
        $this->assertSame($journal->lines->sum('debit'), $journal->lines->sum('credit'));

        $this->assertSame(30000, Account::netDebitByKey('cash'));            // kas masuk
        $this->assertSame(-30000, Account::netDebitByKey('sales'));          // penjualan (kredit)
        $this->assertSame(18000, Account::netDebitByKey('cogs'));            // HPP
        $this->assertSame(60000 - 18000, Account::netDebitByKey('inventory'));

        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_insufficient_stock_rolls_everything_back(): void
    {
        $a = $this->stockedProduct('5', 1000);
        $b = $this->stockedProduct('1', 1000);
        $journalsBefore = Journal::count();

        try {
            app(OrderService::class)->create([
                'items' => [
                    ['product_id' => $a->id, 'qty' => 2],
                    ['product_id' => $b->id, 'qty' => 2], // kurang
                ],
            ], 'pos_android');
            $this->fail('Seharusnya gagal karena stok kurang.');
        } catch (InsufficientStockException $e) {
            $this->assertSame($b->id, $e->product->id);
        }

        $this->assertSame(5000, $a->fresh()->qtyMilli(), 'Stok produk pertama tidak boleh ikut berkurang.');
        $this->assertSame(1000, $b->fresh()->qtyMilli());
        $this->assertSame(0, Order::count());
        $this->assertSame($journalsBefore, Journal::count());
        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_same_uuid_is_idempotent_and_does_not_double_deduct_stock(): void
    {
        $product = $this->stockedProduct('10', 1000);
        $payload = [
            'uuid' => '6f0f0b2e-8c1d-4b8f-9d86-0a5f3c4d7a11',
            'items' => [['product_id' => $product->id, 'qty' => 4]],
        ];

        [$first, $createdFirst] = app(OrderService::class)->create($payload, 'pos_desktop');
        [$second, $createdSecond] = app(OrderService::class)->create($payload, 'pos_desktop');

        $this->assertTrue($createdFirst);
        $this->assertFalse($createdSecond);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(6000, $product->fresh()->qtyMilli());
    }

    public function test_cancel_restores_stock_and_reverses_journals(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);
        $service = app(OrderService::class);

        [$order] = $service->create([
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payment_method' => 'transfer',
        ], 'web');

        $service->cancel($order);
        $service->cancel($order); // dua kali tidak boleh mengembalikan stok dua kali

        $this->assertSame(10000, $product->fresh()->qtyMilli());
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, Account::netDebitByKey('sales'));
        $this->assertSame(0, Account::netDebitByKey('receivable'));
        $this->assertSame(0, Account::netDebitByKey('cogs'));
        $this->assertSame(60000, Account::netDebitByKey('inventory'));
        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_web_order_is_unpaid_until_payment_is_recorded(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);
        $service = app(OrderService::class);

        [$order] = $service->create([
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payment_method' => 'transfer',
            'shipping_fee' => 5000,
        ], 'web');

        $this->assertSame(25000, $order->grand_total);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(25000, Account::netDebitByKey('receivable'));

        $service->recordPayment($order, 25000, 'transfer');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(0, Account::netDebitByKey('receivable'));
        $this->assertSame(25000, Account::netDebitByKey('bank'));
        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_discount_and_tax_are_included_in_journal(): void
    {
        config(['store.tax_rate' => 10]);
        $product = $this->stockedProduct('10', 4000, ['price' => 10000]);

        [$order] = app(OrderService::class)->create([
            'items' => [['product_id' => $product->id, 'qty' => 2, 'discount' => 2000]],
        ], 'pos_desktop');

        // subtotal 20.000 - diskon 2.000 = 18.000; pajak 10% = 1.800; total 19.800
        $this->assertSame(20000, $order->subtotal);
        $this->assertSame(2000, $order->discount_total);
        $this->assertSame(1800, $order->tax_total);
        $this->assertSame(19800, $order->grand_total);
        $this->assertSame(-1800, Account::netDebitByKey('tax_payable'));
        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_opname_posts_adjustment_journal(): void
    {
        $product = $this->stockedProduct('10', 5000);
        app(InventoryService::class)->opname($product, '8', 'hitung ulang');

        $this->assertSame(8000, $product->fresh()->qtyMilli());
        $this->assertSame(10000, Account::netDebitByKey('inventory_adjustment')); // beban 2 x 5.000
        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $this->expectException(UnbalancedJournalException::class);

        app(JournalService::class)->post('adjustment', 'salah', [
            ['account' => 'cash', 'debit' => 1000],
            ['account' => 'sales', 'credit' => 900],
        ]);
    }

    public function test_posted_journals_and_movements_are_immutable(): void
    {
        $product = $this->stockedProduct('1', 1000);
        $journal = Journal::firstOrFail();

        try {
            $journal->update(['description' => 'diubah diam-diam']);
            $this->fail('Jurnal seharusnya tidak bisa diubah.');
        } catch (LogicException) {
            $this->assertNotSame('diubah diam-diam', $journal->fresh()->description);
        }

        $this->expectException(LogicException::class);
        StockMovement::where('product_id', $product->id)->firstOrFail()->delete();
    }

    public function test_reconciliation_detects_tampered_stock_cache(): void
    {
        $product = $this->stockedProduct('10', 1000);
        $product->forceFill(['stock_qty' => '99.000'])->save(); // simulasi edit manual di database

        $result = app(ReconciliationService::class)->run();

        $this->assertFalse($result['ok']);
        $check = collect($result['checks'])->firstWhere('key', 'stock_cache');
        $this->assertFalse($check['ok']);
        $this->assertSame($product->sku, $check['rows'][0]['sku']);
    }
}
