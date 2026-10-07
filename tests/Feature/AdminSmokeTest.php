<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\FixedAsset;
use App\Models\Order;
use App\Models\Product;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\FinanceService;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Services\ReconciliationService;
use App\Services\SalesDocumentService;
use App\Services\StockDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

/** Membuka SEMUA halaman admin (tanpa parameter + halaman detail) dengan data nyata di setiap modul. */
class AdminSmokeTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_every_admin_page_renders_with_data_in_every_module(): void
    {
        $this->seedAccounts();
        $this->actingAs($this->makeUser('admin'));

        $supplier = Supplier::create(['name' => 'CV Maju', 'term_days' => 14]);
        $customer = Customer::create(['name' => 'Budi', 'phone' => '0811']);
        $product = $this->makeProduct(['price' => 15000]);
        $product->unitConversions()->create(['unit' => 'dus', 'factor' => 12]);
        $buy = app(PurchaseService::class)->create(['supplier_id' => $supplier->id, 'payment_method' => 'credit', 'date' => today()->subDays(20)->toDateString(), 'items' => [['product_id' => $product->id, 'qty' => 50, 'price' => 8000]]]);
        $pay = app(PurchaseService::class)->pay($supplier, 10000, 'cash');
        $preturn = app(PurchaseService::class)->createReturn(['purchase_id' => $buy->id, 'supplier_id' => $supplier->id, 'settlement' => 'receivable', 'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 8000]]]);

        $wh = Warehouse::create(['name' => 'Gudang B', 'code' => 'GB']);
        $transfer = app(StockDocumentService::class)->createTransfer(['from_warehouse_id' => Warehouse::mainId(), 'to_warehouse_id' => $wh->id, 'items' => [['product_id' => $product->id, 'qty' => 2]]]);
        $adjustment = app(StockDocumentService::class)->createAdjustment(['reason' => 'damaged', 'items' => [['product_id' => $product->id, 'qty_change' => -1]]]);
        $opname = app(StockDocumentService::class)->startOpname([]);

        [$sale] = app(OrderService::class)->create(['items' => [['product_id' => $product->id, 'qty' => 3]], 'payment_method' => 'cash', 'paid_total' => 20000, 'buyer_id' => $customer->id], 'admin');
        [$kasir] = app(OrderService::class)->create(['items' => [['product_id' => $product->id, 'qty' => 1]], 'payment_method' => 'qris'], 'pos_desktop');
        $web = $this->webOrder($product);
        $sreturn = app(SalesDocumentService::class)->createReturn($sale, ['items' => [['order_item_id' => $sale->items->first()->id, 'qty' => 1]], 'refund_method' => 'cash']);
        $deposit = app(SalesDocumentService::class)->createDeposit(['customer_id' => $customer->id, 'amount' => 50000, 'method' => 'cash']);
        $over = app(SalesDocumentService::class)->recordOverpayment($kasir, 5000, false);
        $asset = app(FinanceService::class)->addAsset(['name' => 'Rak', 'cost' => 1200000, 'life_months' => 12, 'acquired_on' => today()->subMonths(2)->toDateString()]);
        app(FinanceService::class)->depreciate(now()->format('Y-m'));
        $cash = app(FinanceService::class)->closeCash(['date' => today()->toDateString(), 'denominations' => ['100000' => 1]]);
        $expense = app(FinanceService::class)->cashEntry(['kind' => 'expense', 'account_id' => \App\Models\Account::where('key', 'utilities')->value('id') ?? \App\Models\Account::where('type', 'expense')->value('id'), 'amount' => 25000]);
        $manual = app(FinanceService::class)->manualJournal(['description' => 'Koreksi', 'lines' => [['account_id' => \App\Models\Account::where('key', 'cash')->value('id'), 'debit' => 100], ['account_id' => \App\Models\Account::where('key', 'other_income')->value('id'), 'credit' => 100]]]);

        $params = [
            'product' => $product, 'order' => $sale, 'journal' => $manual, 'purchase' => $buy, 'purchaseReturn' => $preturn, 'transfer' => $transfer,
            'saleReturn' => $sreturn, 'deposit' => $deposit, 'closing' => $cash, 'adjustment' => $adjustment, 'opname' => $opname,
            'supplier' => $supplier, 'customer' => $customer, 'group' => $product->group_id ? \App\Models\ProductGroup::find($product->group_id) : null,
        ];

        $skip = ['admin.logout', 'admin.login', 'admin.stock.feed', 'admin.lookup.products'];
        $checked = 0;
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true) || in_array($name, $skip, true)) {
                continue;
            }

            $args = [];
            foreach ($route->parameterNames() as $param) {
                $model = $params[$param] ?? null;
                if (! $model) {
                    continue 2; // parameter yang tidak kita sediakan (mis. artikel, ulasan): sudah diuji di tes masing-masing
                }
                $args[$param] = $model;
            }

            $url = route($name, $args);
            $response = $this->get($url);
            $checked++;
            if ($response->status() !== 200) {
                $failures[] = $name.' '.$url.' → '.$response->status().' '.substr((string) ($response->exception?->getMessage() ?? ''), 0, 200);
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
        $this->assertGreaterThan(80, $checked, 'Smoke test harus mencakup semua halaman admin.');

        // Halaman dengan filter dan unduhan CSV.
        foreach ([
            'admin.purchases.index', 'admin.payables.index', 'admin.sales.index', 'admin.sales.items', 'admin.sales.cancelled', 'admin.sales.pos', 'admin.recap.index', 'admin.balances.index',
            'admin.stock.mutation', 'admin.journals.index', 'admin.reports.income', 'admin.reports.balance', 'admin.reports.trial', 'admin.expenses.index', 'admin.sale-returns.index', 'admin.adjustments.index',
        ] as $name) {
            $this->get(route($name, ['export' => 'csv']))->assertOk();
        }
        $this->get(route('admin.reports.ledger', ['account' => \App\Models\Account::where('key', 'cash')->value('id'), 'export' => 'csv']))->assertOk();
        $this->get(route('admin.stock.card', ['product' => $product->id]))->assertOk()->assertSee($product->name);
        $this->get(route('admin.cards.payable', ['supplier' => $supplier->id]))->assertOk()->assertSee('Saldo akhir');
        $this->get(route('admin.cards.receivable', ['customer' => $customer->id]))->assertOk()->assertSee('Kartu piutang');
    }

    private function webOrder(Product $product): Order
    {
        [$order] = app(OrderService::class)->create([
            'items' => [['product_id' => $product->id, 'qty' => 2]], 'payment_method' => 'transfer', 'customer_name' => 'Ani', 'customer_phone' => '0822',
        ], 'web');

        return $order;
    }
}
