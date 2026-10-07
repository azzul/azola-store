<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\Order;
use App\Models\Product;
use App\Models\PriceLevel;
use App\Models\Purchase;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\ReconciliationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

/** Alur lewat formulir admin (HTTP) untuk setiap modul, lalu pembukuan harus tetap seimbang. */
class AdminFlowsTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
        $this->actingAs($this->makeUser('admin'));
    }

    private function assertBooksOk(): void
    {
        $bad = collect(app(ReconciliationService::class)->run()['checks'])->where('ok', false)->pluck('title')->all();
        $this->assertSame([], $bad, 'Rekonsiliasi gagal');
        $trial = app(ReportService::class)->trial('2000-01-01', '2100-01-01');
        $this->assertSame($trial['debit'], $trial['credit']);
        $this->assertTrue(app(ReportService::class)->balanceSheet('2100-01-01')['totals']['balanced']);
    }

    public function test_purchase_create_edit_return_transfer_via_forms(): void
    {
        $s = Supplier::create(['name' => 'CV Maju', 'term_days' => 7]);
        $p = $this->makeProduct(['price' => 15000]);

        $this->post(route('admin.purchases.store'), [
            'supplier_id' => $s->id, 'date' => today()->toDateString(), 'payment_method' => 'credit',
            'items' => [['product_id' => $p->id, 'unit' => 'pcs', 'qty' => 10, 'price' => 8000]],
        ])->assertSessionHasNoErrors();
        $buy = Purchase::firstOrFail();
        $this->assertSame(80000, $s->payable());

        $this->put(route('admin.purchases.update', $buy), [
            'supplier_id' => $s->id, 'date' => today()->toDateString(), 'payment_method' => 'credit',
            'items' => [['product_id' => $p->id, 'unit' => 'pcs', 'qty' => 12, 'price' => 8000]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(96000, $s->fresh()->payable());
        $this->assertSame(12000, $p->fresh()->qtyMilli());

        $wh = Warehouse::create(['name' => 'Gudang B', 'code' => 'GB']);
        $this->post(route('admin.transfers.store'), ['from_warehouse_id' => Warehouse::mainId(), 'to_warehouse_id' => $wh->id, 'date' => today()->toDateString(), 'items' => [['product_id' => $p->id, 'unit' => 'pcs', 'qty' => 4]]])->assertSessionHasNoErrors();
        $t = StockTransfer::firstOrFail();
        $this->post(route('admin.transfers.receive', $t), ['date' => today()->toDateString(), 'received' => [$p->id => 3]])->assertSessionHasNoErrors();
        $this->assertSame('received', $t->fresh()->status);
        $this->assertBooksOk();
    }

    public function test_sales_flow_return_deposit_overpayment_via_forms(): void
    {
        $p = $this->stockedProduct('20', 6000, ['price' => 10000]);
        $c = Customer::create(['name' => 'Budi', 'phone' => '0811']);

        $this->post(route('admin.sales.store'), [
            'buyer_id' => $c->id, 'payment_method' => 'cash', 'paid_total' => 10000,
            'items' => [['product_id' => $p->id, 'unit' => 'pcs', 'qty' => 5, 'price' => 10000]],
        ])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame('admin', $order->channel);
        $this->assertSame(40000, $order->outstanding());
        $this->assertSame(15000, $p->fresh()->qtyMilli());

        // Harga di atas daftar ditolak.
        $this->post(route('admin.sales.store'), ['payment_method' => 'cash', 'items' => [['product_id' => $p->id, 'qty' => 1, 'price' => 99999]]])->assertSessionHasErrors('items');

        $this->post(route('admin.sale-returns.store'), [
            'order_id' => $order->id, 'date' => today()->toDateString(), 'refund_method' => 'cash',
            'items' => [$order->items->first()->id => ['qty' => 1, 'restock' => 1]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(10000, $order->fresh()->returned_total);
        $this->assertSame(16000, $p->fresh()->qtyMilli());

        $this->post(route('admin.deposits.store'), ['customer_id' => $c->id, 'amount' => 20000, 'method' => 'cash', 'date' => today()->toDateString()])->assertSessionHasNoErrors();
        $dp = CustomerDeposit::firstOrFail();
        $this->post(route('admin.deposits.apply', $dp), ['order_id' => $order->id, 'amount' => 20000])->assertSessionHasNoErrors();
        $this->assertSame(10000, $order->fresh()->outstanding());
        $this->post(route('admin.orders.pay', $order), ['amount' => 10000, 'method' => 'transfer'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->payment_status);

        $this->post(route('admin.overpayments.store'), ['order_id' => $order->id, 'amount' => 3000, 'refund' => 'now'])->assertSessionHasNoErrors();
        $this->assertSame(0, CustomerDeposit::where('kind', 'overpay')->first()->balance());
        $this->assertBooksOk();
    }

    public function test_online_order_lifecycle_via_forms(): void
    {
        $p = $this->stockedProduct('10', 6000, ['price' => 10000]);
        [$order] = app(OrderService::class)->create(['items' => [['product_id' => $p->id, 'qty' => 2]], 'payment_method' => 'transfer', 'customer_name' => 'Ani', 'customer_phone' => '0822'], 'web');
        $this->assertSame('new', $order->fulfillment);

        $this->get(route('admin.online.new'))->assertOk()->assertSee($order->number);
        $this->post(route('admin.online.ship', $order), ['courier' => 'JNE', 'tracking_no' => 'X1'])->assertSessionHasErrors('order'); // belum lunas

        $this->post(route('admin.orders.pay', $order), ['amount' => $order->grand_total, 'method' => 'transfer'])->assertSessionHasNoErrors();
        $this->assertSame('process', $order->fresh()->fulfillment);
        $this->get(route('admin.online.process'))->assertSee($order->number);

        $this->post(route('admin.online.ship', $order), ['courier' => 'JNE', 'tracking_no' => 'X1'])->assertSessionHasNoErrors();
        $this->get(route('admin.online.shipped'))->assertSee('X1');
        $this->post(route('admin.online.complete', $order))->assertSessionHasNoErrors();
        $this->assertSame('done', $order->fresh()->fulfillment);
        $this->get(route('admin.online.done'))->assertSee($order->number);

        [$other] = app(OrderService::class)->create(['items' => [['product_id' => $p->id, 'qty' => 1]], 'payment_method' => 'cod', 'customer_name' => 'Cici', 'customer_phone' => '0833'], 'web');
        $this->post(route('admin.orders.cancel', $other), ['reason' => 'tes'])->assertSessionHasNoErrors();
        $this->get(route('admin.online.cancelled'))->assertSee($other->number);
        $this->assertBooksOk();
    }

    public function test_finance_stock_and_report_forms(): void
    {
        $p = $this->stockedProduct('10', 6000, ['price' => 10000]);

        $this->post(route('admin.adjustments.store'), ['date' => today()->toDateString(), 'reason' => 'damaged', 'items' => [['product_id' => $p->id, 'unit' => 'pcs', 'qty_change' => -2]]])->assertSessionHasNoErrors();
        $this->assertSame(8000, $p->fresh()->qtyMilli());

        $this->post(route('admin.opnames.store'), ['date' => today()->toDateString()])->assertSessionHasNoErrors();
        $op = StockOpname::firstOrFail();
        $item = $op->items()->first();
        $this->post(route('admin.opnames.save', $op), ['counts' => [$item->id => 7], 'finalize' => 1])->assertSessionHasNoErrors();
        $this->assertSame(7000, $p->fresh()->qtyMilli());
        $this->assertTrue($op->fresh()->isFinal());

        $this->post(route('admin.expenses.store'), ['kind' => 'expense', 'account_id' => Account::where('type', 'expense')->value('id'), 'via' => 'cash', 'amount' => 15000, 'date' => today()->toDateString()])->assertSessionHasNoErrors();
        $j = Journal::where('type', 'expense')->firstOrFail();
        $this->post(route('admin.expenses.cancel', $j))->assertSessionHasNoErrors();
        $this->assertTrue($j->fresh()->isReversed());

        $this->post(route('admin.assets.store'), ['name' => 'Rak', 'acquired_on' => today()->subMonths(1)->toDateString(), 'cost' => 1200000, 'salvage' => 0, 'life_months' => 12, 'paid_via' => 'equity'])->assertSessionHasNoErrors();
        $this->post(route('admin.assets.depreciate'), ['period' => now()->format('Y-m')])->assertSessionHasNoErrors();
        $this->assertSame(200000, (int) FixedAsset::first()->depreciated);
        $this->post(route('admin.assets.dispose', FixedAsset::first()), ['proceeds' => 900000, 'via' => 'cash'])->assertSessionHasNoErrors();

        $this->post(route('admin.cash.store'), ['date' => today()->toDateString(), 'denominations' => ['100000' => 2], 'other' => 0])->assertSessionHasNoErrors();
        $this->post(route('admin.cash.store'), ['date' => today()->toDateString(), 'denominations' => ['100000' => 2]])->assertSessionHasErrors('date');

        $cash = Account::where('key', 'cash')->value('id');
        $other = Account::where('key', 'other_income')->value('id');
        $this->post(route('admin.adjustment-journals.store'), ['date' => today()->toDateString(), 'description' => 'Koreksi', 'lines' => [['account_id' => $cash, 'debit' => 500], ['account_id' => $other, 'credit' => 500]]])->assertSessionHasNoErrors();
        $this->post(route('admin.adjustment-journals.store'), ['date' => today()->toDateString(), 'description' => 'Salah', 'lines' => [['account_id' => $cash, 'debit' => 500], ['account_id' => $other, 'credit' => 400]]])->assertSessionHasErrors('lines');

        $this->get(route('admin.reports.balance'))->assertOk()->assertSee('Seimbang');
        $this->get(route('admin.reports.trial'))->assertOk()->assertSee('seimbang');
        $this->assertBooksOk();
    }
}
