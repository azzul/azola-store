<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\FinanceService;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Services\ReconciliationService;
use App\Services\ReportService;
use App\Services\SalesDocumentService;
use App\Services\StockDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class ErpServicesTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    private PurchaseService $purchases;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
        $this->purchases = app(PurchaseService::class);
    }

    private function supplier(string $name = 'CV Sumber Rejeki'): Supplier
    {
        return Supplier::create(['name' => $name, 'term_days' => 14]);
    }

    private function bal(string $key): int
    {
        return Account::where('key', $key)->first()->balance();
    }

    private function assertBooksOk(): void
    {
        $result = app(ReconciliationService::class)->run();
        $bad = collect($result['checks'])->where('ok', false)->map(fn ($c) => $c['title'].' '.json_encode($c['rows']))->all();
        $this->assertSame([], $bad, 'Rekonsiliasi gagal');

        $trial = app(ReportService::class)->trial('2000-01-01', '2100-01-01');
        $this->assertSame($trial['debit'], $trial['credit'], 'Neraca saldo tidak seimbang');

        $sheet = app(ReportService::class)->balanceSheet('2100-01-01');
        $this->assertTrue($sheet['totals']['balanced'], 'Neraca tidak seimbang');
    }

    public function test_cash_purchase_updates_stock_cost_and_journal(): void
    {
        $p = $this->makeProduct(['price' => 15000]);
        $purchase = $this->purchases->create([
            'supplier_id' => $this->supplier()->id, 'date' => today()->toDateString(), 'payment_method' => 'cash',
            'items' => [['product_id' => $p->id, 'qty' => 10, 'price' => 8000]],
        ]);

        $this->assertSame(80000, $purchase->grand_total);
        $this->assertSame(80000, $purchase->paid_total);
        $this->assertSame(10000, $p->fresh()->qtyMilli());
        $this->assertSame(8000, $p->fresh()->cost);
        $this->assertSame(80000, $this->bal('inventory'));
        $this->assertSame(-80000, Account::where('key', 'cash')->first()->netDebit());
        $this->assertStringStartsWith('PB-', $purchase->number);
        $this->assertBooksOk();
    }

    public function test_unit_conversion_and_discount(): void
    {
        $p = $this->makeProduct();
        $p->unitConversions()->create(['unit' => 'dus', 'factor' => 12]);

        $purchase = $this->purchases->create([
            'payment_method' => 'cash', 'discount' => 6000,
            'items' => [['product_id' => $p->id, 'unit' => 'dus', 'qty' => 2, 'price' => 60000]],
        ]);

        $this->assertSame(114000, $purchase->grand_total);
        $this->assertSame(24000, $p->fresh()->qtyMilli(), '2 dus x 12 = 24 pcs');
        $this->assertSame(4750, $p->fresh()->cost, '114000 / 24');
        $this->assertBooksOk();
    }

    public function test_credit_purchase_payable_and_payment_fifo(): void
    {
        $s = $this->supplier();
        $p = $this->makeProduct();

        $a = $this->purchases->create(['supplier_id' => $s->id, 'payment_method' => 'credit', 'date' => today()->subDays(10)->toDateString(), 'items' => [['product_id' => $p->id, 'qty' => 5, 'price' => 10000]]]);
        $b = $this->purchases->create(['supplier_id' => $s->id, 'payment_method' => 'credit', 'down_payment' => 10000, 'down_payment_via' => 'bank', 'items' => [['product_id' => $p->id, 'qty' => 5, 'price' => 10000]]]);

        $this->assertSame(90000, $s->payable());
        $this->assertSame(90000, $this->bal('payable'));
        $this->assertSame(10000, $b->paid_total);

        $payment = $this->purchases->pay($s, 60000, 'cash');
        $this->assertSame(30000, $s->fresh()->payable());
        $this->assertSame(50000, $a->fresh()->paid_total, 'tertua lunas dulu');
        $this->assertSame(20000, $b->fresh()->paid_total);

        try {
            $this->purchases->pay($s, 99999999, 'cash');
            $this->fail('harus menolak bayar melebihi hutang');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->purchases->cancelPayment($payment);
        $this->assertSame(90000, $s->fresh()->payable());
        $this->assertSame(0, $a->fresh()->paid_total);
        $this->assertBooksOk();
    }

    public function test_edit_purchase_changes_qty_price_and_keeps_books_balanced(): void
    {
        $p = $this->makeProduct();
        $q = $this->makeProduct();
        $purchase = $this->purchases->create(['payment_method' => 'cash', 'items' => [
            ['product_id' => $p->id, 'qty' => 10, 'price' => 5000], ['product_id' => $q->id, 'qty' => 4, 'price' => 2500],
        ]]);

        $this->purchases->update($purchase, ['payment_method' => 'cash', 'items' => [
            ['product_id' => $p->id, 'qty' => 12, 'price' => 6000],
        ]]);

        $this->assertSame(12000, $p->fresh()->qtyMilli());
        $this->assertSame(6000, $p->fresh()->cost);
        $this->assertSame(0, $q->fresh()->qtyMilli());
        $this->assertSame(72000, $purchase->fresh()->grand_total);
        $this->assertSame(72000, $this->bal('inventory'));
        $this->assertBooksOk();

        $this->purchases->cancel($purchase);
        $this->assertSame(0, $p->fresh()->qtyMilli());
        $this->assertSame(0, $this->bal('inventory'));
        $this->assertSame('cancelled', $purchase->fresh()->status);
        $this->assertBooksOk();
    }

    public function test_cannot_edit_purchase_below_already_paid(): void
    {
        $s = $this->supplier();
        $p = $this->makeProduct();
        $purchase = $this->purchases->create(['supplier_id' => $s->id, 'payment_method' => 'credit', 'items' => [['product_id' => $p->id, 'qty' => 10, 'price' => 1000]]]);
        $this->purchases->pay($s, 8000, 'cash');

        $this->expectException(ValidationException::class);
        $this->purchases->update($purchase, ['supplier_id' => $s->id, 'payment_method' => 'credit', 'items' => [['product_id' => $p->id, 'qty' => 5, 'price' => 1000]]]);
    }

    public function test_purchase_return_settlements(): void
    {
        $s = $this->supplier();
        $p = $this->makeProduct();
        $purchase = $this->purchases->create(['supplier_id' => $s->id, 'payment_method' => 'credit', 'items' => [['product_id' => $p->id, 'qty' => 10, 'price' => 10000]]]);

        // Potong hutang
        $r1 = $this->purchases->createReturn(['purchase_id' => $purchase->id, 'settlement' => 'payable', 'items' => [['product_id' => $p->id, 'qty' => 3, 'price' => 10000]]]);
        $this->assertSame(70000, $s->fresh()->payable());
        $this->assertSame(7000, $p->fresh()->qtyMilli());
        $this->assertSame(10000, $p->fresh()->cost);
        $this->assertSame(70000, $this->bal('inventory'));

        // Tidak boleh retur melebihi pembelian
        try {
            $this->purchases->createReturn(['purchase_id' => $purchase->id, 'items' => [['product_id' => $p->id, 'qty' => 8, 'price' => 10000]]]);
            $this->fail('harus menolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        // Jadi piutang supplier, lalu diterima
        $r2 = $this->purchases->createReturn(['purchase_id' => $purchase->id, 'settlement' => 'receivable', 'items' => [['product_id' => $p->id, 'qty' => 2, 'price' => 10000]]]);
        $this->assertSame(20000, $s->fresh()->receivable());
        $this->purchases->receive($s, 15000, 'bank');
        $this->assertSame(5000, $s->fresh()->receivable());
        $this->assertSame(5000, $this->bal('supplier_receivable'));

        $this->assertBooksOk();

        // Batal retur potong hutang mengembalikan hutang dan stok
        $this->purchases->cancelReturn($r1);
        $this->assertSame(10 - 2, $p->fresh()->qtyMilli() / 1000);
        $this->assertBooksOk();
    }

    public function test_transfer_between_warehouses_with_shortage(): void
    {
        $p = $this->stockedProduct('10', 5000);
        $gudang = Warehouse::create(['code' => 'GD1', 'name' => 'Gudang Belakang']);
        $svc = app(StockDocumentService::class);

        $t = $svc->createTransfer(['from_warehouse_id' => Warehouse::mainId(), 'to_warehouse_id' => $gudang->id, 'items' => [['product_id' => $p->id, 'qty' => 6]]]);
        $this->assertSame(4000, $p->fresh()->qtyMilli(), 'stok jual berkurang saat dikirim');
        $this->assertBooksOk(); // barang dalam perjalanan tetap dinilai

        $svc->receiveTransfer($t, [$p->id => 5]);
        $this->assertSame(5000, (int) round((float) \DB::table('stock_balances')->where(['product_id' => $p->id, 'warehouse_id' => $gudang->id])->value('qty') * 1000));
        $this->assertSame(45000, $this->bal('inventory'), 'kekurangan 1 unit dihapus dari persediaan');
        $this->assertSame(5000, $this->bal('inventory_shrinkage'));
        $this->assertBooksOk();

        // Gudang lain tidak dijual: kasir hanya melihat gudang jual.
        $svc->createTransfer(['from_warehouse_id' => $gudang->id, 'to_warehouse_id' => Warehouse::mainId(), 'items' => [['product_id' => $p->id, 'qty' => 5]]]);
        $this->assertBooksOk();
    }

    public function test_adjustment_and_opname(): void
    {
        $p = $this->stockedProduct('10', 4000);
        $svc = app(StockDocumentService::class);

        $svc->createAdjustment(['reason' => 'damaged', 'items' => [['product_id' => $p->id, 'qty_change' => -2]]]);
        $this->assertSame(8000, $p->fresh()->qtyMilli());
        $this->assertSame(8000, $this->bal('inventory_shrinkage'));

        $opname = $svc->startOpname([]);
        $item = $opname->items()->where('product_id', $p->id)->first();
        $svc->saveCounts($opname->fresh('items'), [$item->id => '9']);
        $svc->finalizeOpname($opname->fresh());

        $this->assertSame(9000, $p->fresh()->qtyMilli());
        $this->assertSame(-4000, $this->bal('inventory_adjustment'), 'selisih lebih mengurangi beban');
        $this->assertSame(4000, $opname->fresh()->value_diff);
        $this->assertBooksOk();

        $this->expectException(\App\Exceptions\InsufficientStockException::class);
        $svc->createAdjustment(['reason' => 'lost', 'items' => [['product_id' => $p->id, 'qty_change' => -99]]]);
    }

    public function test_sale_return_deposit_and_overpayment(): void
    {
        $p = $this->stockedProduct('10', 6000, ['price' => 10000]);
        $orders = app(OrderService::class);
        $sales = app(SalesDocumentService::class);

        [$order] = $orders->create([
            'customer_name' => 'Bu Rina', 'customer_phone' => '081200001111', 'payment_method' => 'cash', 'paid_total' => 10000,
            'items' => [['product_id' => $p->id, 'qty' => 4]],
        ], 'admin', $this->makeUser());

        $this->assertNotNull($order->buyer_id, 'master customer dibuat dari telepon');
        $this->assertSame(30000, $order->outstanding());
        $this->assertSame(1, $order->payments()->count());

        // Retur 2 unit: 20000 mengurangi piutang (sisa 30000 -> 10000), tidak ada uang keluar.
        $return = $sales->createReturn($order, ['items' => [['order_item_id' => $order->items->first()->id, 'qty' => 2]]]);
        $this->assertSame(20000, $return->total);
        $this->assertSame(20000, $return->offset_total);
        $this->assertSame(0, $return->paid_out);
        $this->assertSame(8000, $p->fresh()->qtyMilli());
        $this->assertSame(10000, $order->fresh()->outstanding());
        $this->assertBooksOk();

        // Retur 2 lagi: 10000 menutup piutang, 10000 dikembalikan tunai.
        $r2 = $sales->createReturn($order->fresh(), ['refund_method' => 'cash', 'items' => [['order_item_id' => $order->items->first()->id, 'qty' => 2]]]);
        $this->assertSame(10000, $r2->offset_total);
        $this->assertSame(10000, $r2->paid_out);
        $this->assertSame(0, $order->fresh()->outstanding());
        $this->assertBooksOk();

        // Melebihi qty ditolak.
        try {
            $sales->createReturn($order->fresh(), ['refund_method' => 'cash', 'items' => [['order_item_id' => $order->items->first()->id, 'qty' => 1]]]);
            $this->fail('harus menolak');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Kelebihan transfer: disimpan sebagai DP lalu dipakai untuk pesanan lain.
        $dep = $sales->recordOverpayment($order->fresh(), 25000, false);
        $this->assertSame(25000, $dep->balance());
        $this->assertSame(25000, $this->bal('customer_deposit'));

        [$o2] = $orders->create(['customer_name' => 'Bu Rina', 'customer_phone' => '081200001111', 'payment_method' => 'cash', 'paid_total' => 0, 'items' => [['product_id' => $p->id, 'qty' => 2]]], 'admin');
        $sales->applyDeposit($dep->fresh(), $o2, 20000);
        $this->assertSame('paid', $o2->fresh()->payment_status);
        $this->assertSame(5000, $dep->fresh()->balance());

        $sales->refundDeposit($dep->fresh(), 5000, 'transfer');
        $this->assertSame(0, $dep->fresh()->balance());
        $this->assertSame(0, $this->bal('customer_deposit'));
        $this->assertBooksOk();

        // Kartu piutang customer mengikuti jurnal.
        $card = app(ReportService::class)->customerReceivableCard(Customer::find($order->buyer_id), '2000-01-01', '2100-01-01');
        $this->assertSame(0, $card['closing']);
    }

    public function test_cash_closing_depreciation_and_manual_journal(): void
    {
        $fin = app(FinanceService::class);
        $fin->cashEntry(['kind' => 'income', 'account_id' => Account::where('key', 'other_income')->value('id'), 'amount' => 500000, 'via' => 'cash']);

        $summary = $fin->cashSummary(today()->toDateString());
        $this->assertSame(500000, $summary['expected']);

        $closing = $fin->closeCash(['date' => today()->toDateString(), 'denominations' => [100000 => 4, 50000 => 1]]);
        $this->assertSame(450000, $closing->counted);
        $this->assertSame(-50000, $closing->difference);
        $this->assertSame(450000, Account::where('key', 'cash')->first()->netDebit());
        $this->assertSame(50000, $this->bal('cash_over_short'));

        try {
            $fin->closeCash(['date' => today()->toDateString()]);
            $this->fail('tidak boleh tutup dua kali');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $asset = $fin->addAsset(['name' => 'Rak Besi', 'acquired_on' => today()->subMonths(2)->startOfMonth()->toDateString(), 'cost' => 1200000, 'salvage' => 0, 'life_months' => 12, 'paid_via' => 'equity']);
        $result = $fin->depreciate(today()->format('Y-m'));
        $this->assertSame(3, $result['runs'], '3 bulan dikejar');
        $this->assertSame(300000, $result['amount']);
        $this->assertSame(0, $fin->depreciate(today()->format('Y-m'))['runs'], 'idempoten');
        $this->assertSame(900000, $asset->fresh()->bookValue());

        $debitId = Account::where('key', 'operating_expense')->value('id');
        $creditId = Account::where('key', 'cash')->value('id');
        $fin->manualJournal(['description' => 'Koreksi', 'lines' => [['account_id' => $debitId, 'debit' => 1000], ['account_id' => $creditId, 'credit' => 1000]]]);

        $this->expectException(ValidationException::class);
        $fin->manualJournal(['description' => 'Salah', 'lines' => [['account_id' => $debitId, 'debit' => 1000], ['account_id' => $creditId, 'credit' => 900]]]);
    }

    public function test_online_order_fulfillment_flow(): void
    {
        $p = $this->stockedProduct('10', 6000, ['price' => 10000]);
        $orders = app(OrderService::class);

        [$order] = $orders->create(['customer_name' => 'Web', 'customer_phone' => '08123', 'payment_method' => 'transfer', 'delivery_method' => 'ship', 'items' => [['product_id' => $p->id, 'qty' => 1]]], 'web');
        $this->assertSame('new', $order->fulfillment);

        try {
            $orders->ship($order, 'JNE', 'RESI1');
            $this->fail('belum lunas');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $orders->recordPayment($order, $order->grand_total, 'transfer');
        $this->assertSame('process', $order->fresh()->fulfillment, 'lunas otomatis perlu proses');

        $orders->ship($order->fresh(), 'JNE', 'RESI1');
        $this->assertSame('shipped', $order->fresh()->fulfillment);

        $orders->complete($order->fresh());
        $this->assertSame('done', $order->fresh()->fulfillment);
        $this->assertBooksOk();
    }

    public function test_reports_balance(): void
    {
        $p = $this->stockedProduct('10', 6000, ['price' => 10000]);
        app(OrderService::class)->create(['payment_method' => 'cash', 'items' => [['product_id' => $p->id, 'qty' => 3]]], 'pos_desktop');

        $r = app(ReportService::class);
        $is = $r->incomeStatement('2000-01-01', '2100-01-01');
        $this->assertSame(30000, $is['totals']['revenue']);
        $this->assertSame(18000, $is['totals']['cogs']);
        $this->assertSame(12000, $is['totals']['net']);

        $recap = $r->paymentRecap('2000-01-01', '2100-01-01');
        $this->assertSame(30000, $recap['by_method']['cash']['total']);
        $this->assertSame(30000, $recap['by_channel']['kasir']['total']);

        $card = $r->stockCard($p, '2000-01-01', '2100-01-01');
        $this->assertSame(7000, $card['closing']);
        $this->assertCount(2, $card['rows']);

        $mut = $r->stockMutation('2000-01-01', '2100-01-01');
        $this->assertSame(10000, $mut->first()['in']);
        $this->assertSame(3000, $mut->first()['out']);
        $this->assertBooksOk();
    }
}
