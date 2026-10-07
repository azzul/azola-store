<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use App\Services\StockDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class AdminErpPagesTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
        $this->actingAs($this->makeUser('admin'));
    }

    /** @return array{0: Supplier, 1: Product, 2: Purchase} */
    private function world(): array
    {
        $s = Supplier::create(['name' => 'CV Maju', 'term_days' => 14]);
        Customer::create(['name' => 'Budi', 'phone' => '0811']);
        $p = $this->makeProduct(['price' => 15000]);
        $buy = app(PurchaseService::class)->create([
            'supplier_id' => $s->id, 'payment_method' => 'credit', 'date' => today()->subDays(20)->toDateString(),
            'items' => [['product_id' => $p->id, 'qty' => 10, 'price' => 8000]],
        ]);

        return [$s, $p, $buy];
    }

    public function test_master_and_purchasing_pages_render_with_data(): void
    {
        [$s, $p, $buy] = $this->world();
        $wh = Warehouse::create(['name' => 'Gudang B', 'code' => 'GB']);
        $transfer = app(StockDocumentService::class)->createTransfer([
            'from_warehouse_id' => Warehouse::where('is_main', true)->value('id') ?? Warehouse::first()->id,
            'to_warehouse_id' => $wh->id, 'items' => [['product_id' => $p->id, 'qty' => 2]],
        ]);

        foreach ([
            route('admin.variations.index'), route('admin.units.index'), route('admin.conversions.index'), route('admin.prices.index'),
            route('admin.accounts.index'), route('admin.warehouses.index'),
            route('admin.suppliers.index'), route('admin.suppliers.create'), route('admin.suppliers.show', $s), route('admin.suppliers.edit', $s),
            route('admin.customers.index'), route('admin.customers.create'),
            route('admin.purchases.index'), route('admin.purchases.create'), route('admin.purchases.show', $buy), route('admin.purchases.edit', $buy),
            route('admin.purchase-returns.index'), route('admin.purchase-returns.create'), route('admin.purchase-returns.create', ['purchase' => $buy->id]),
            route('admin.transfers.index'), route('admin.transfers.create'), route('admin.transfers.show', $transfer),
            route('admin.payables.index'), route('admin.supplier-payments.index'), route('admin.supplier-payments.create'),
            route('admin.supplier-payments.create', ['supplier' => $s->id]),
            route('admin.supplier-receivables.index'), route('admin.cards.payable'), route('admin.cards.payable', ['supplier' => $s->id]),
            route('admin.payables.index', ['export' => 'csv']),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_purchase_return_pay_and_cancel_through_http(): void
    {
        [$s, $p, $buy] = $this->world();

        $this->post(route('admin.purchase-returns.store'), [
            'purchase_id' => $buy->id, 'supplier_id' => $s->id, 'date' => today()->toDateString(), 'settlement' => 'payable',
            'items' => [['product_id' => $p->id, 'qty' => 2, 'price' => 8000]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(64000, $s->fresh()->payable());

        $this->post(route('admin.supplier-payments.store'), [
            'supplier_id' => $s->id, 'amount' => 64000, 'method' => 'cash', 'date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $s->fresh()->payable());

        // Faktur yang sudah dibayar/diretur tidak boleh dibatalkan sebelum pembayarannya dibatalkan.
        $this->post(route('admin.purchases.cancel', $buy))->assertSessionHasErrors();
    }
}
