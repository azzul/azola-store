<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\ReconciliationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
    }

    public function test_demo_seed_produces_accurate_books(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(5, Product::count());
        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
        $this->get('/')->assertOk();
    }

    public function test_home_has_seo_basics(): void
    {
        $this->stockedProduct('5', 1000, ['name' => 'Kopi Robusta', 'is_featured' => true]);

        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('og:title', $html);
        $this->assertStringContainsString('"@type":"Store"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('Kopi Robusta', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'Satu h1 per halaman.');
    }

    public function test_product_page_marks_availability_from_live_stock(): void
    {
        $inStock = $this->stockedProduct('5', 1000, ['name' => 'Gula Pasir']);
        $soldOut = $this->makeProduct(['name' => 'Garam Halus']);

        $this->get($inStock->url())->assertOk()
            ->assertSee('"availability":"https://schema.org/InStock"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('data-stock-id="'.$inStock->id.'"', false);

        $this->get($soldOut->url())->assertOk()
            ->assertSee('"availability":"https://schema.org/OutOfStock"', false)
            ->assertSee('Stok habis');
    }

    public function test_hidden_products_are_not_public(): void
    {
        $offline = $this->stockedProduct('5', 1000, ['is_online' => false, 'name' => 'Khusus Kasir']);

        $this->get($offline->url())->assertNotFound();
        $this->get('/produk')->assertOk()->assertDontSee('Khusus Kasir');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($offline->slug);
    }

    public function test_catalog_search_and_noindex_for_filtered_results(): void
    {
        $this->stockedProduct('5', 1000, ['name' => 'Teh Melati']);
        $this->stockedProduct('5', 1000, ['name' => 'Kopi Arabika']);

        $this->get('/produk?q=Melati')->assertOk()
            ->assertSee('Teh Melati')->assertDontSee('Kopi Arabika')
            ->assertSee('noindex,follow', false);

        $this->get('/produk')->assertOk()->assertSee('index,follow', false)->assertDontSee('noindex', false);
    }

    public function test_checkout_creates_order_reserves_stock_and_survives_double_submit(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);

        $this->post(route('cart.add', $product->slug), ['qty' => 3])->assertRedirect();
        $this->get('/checkout')->assertOk();

        $form = [
            'customer_name' => 'Budi',
            'customer_phone' => '0812-3456-7890',
            'delivery_method' => 'ship',
            'customer_address' => 'Jl. Melati 1, Semarang',
            'payment_method' => 'transfer',
        ];

        $response = $this->post('/checkout', $form);
        $order = Order::firstOrFail();
        $response->assertRedirect(route('order.show', $order->uuid));

        $this->assertSame('web', $order->channel);
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(30000 + 15000, $order->grand_total); // ongkir flat
        $this->assertSame(7000, $product->fresh()->qtyMilli());

        $this->get(route('order.show', $order->uuid))->assertOk()->assertSee($order->number);

        // Kirim ulang form yang sama (klik dua kali / tombol back): tidak boleh ada pesanan kedua.
        $this->post('/checkout', $form);
        $this->assertSame(1, Order::count());
        $this->assertSame(7000, $product->fresh()->qtyMilli());

        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_checkout_rejects_quantity_above_stock_without_side_effects(): void
    {
        $product = $this->stockedProduct('2', 1000);

        $this->post(route('cart.add', $product->slug), ['qty' => 5]);
        $this->get('/checkout')->assertOk();
        $this->post('/checkout', [
            'customer_name' => 'Sari', 'customer_phone' => '08123456789',
            'delivery_method' => 'pickup', 'payment_method' => 'cod',
        ])->assertRedirect(route('cart.index'))->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::count());
        $this->assertSame(2000, $product->fresh()->qtyMilli());
    }

    public function test_checkout_validation_and_honeypot(): void
    {
        $product = $this->stockedProduct('5', 1000);
        $this->post(route('cart.add', $product->slug));
        $this->get('/checkout');

        $this->post('/checkout', ['delivery_method' => 'ship', 'payment_method' => 'transfer'])
            ->assertSessionHasErrors(['customer_name', 'customer_phone', 'customer_address']);

        $this->post('/checkout', [
            'website' => 'spam', 'customer_name' => 'Bot', 'customer_phone' => '0812345678',
            'delivery_method' => 'pickup', 'payment_method' => 'cod',
        ])->assertRedirect(route('home'));
        $this->assertSame(0, Order::count());
    }

    public function test_free_shipping_above_threshold(): void
    {
        config(['store.shipping.free_over' => 20000]);
        $product = $this->stockedProduct('10', 1000, ['price' => 10000]);

        $this->post(route('cart.add', $product->slug), ['qty' => 2]);
        $this->get('/checkout');
        $this->post('/checkout', [
            'customer_name' => 'Rina', 'customer_phone' => '08123456789',
            'delivery_method' => 'ship', 'customer_address' => 'Jl. Kenanga 2',
            'payment_method' => 'transfer',
        ]);

        $this->assertSame(0, Order::firstOrFail()->shipping_fee);
    }

    public function test_public_stock_feed_reports_changes_without_leaking_cost(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);

        $cursor = $this->getJson('/api/v1/public/stock-feed')->assertOk()->json('cursor');

        $this->post(route('cart.add', $product->slug), ['qty' => 6]);
        $this->get('/checkout');
        $this->post('/checkout', [
            'customer_name' => 'Dewi', 'customer_phone' => '08123456789',
            'delivery_method' => 'pickup', 'payment_method' => 'cod',
        ]);

        $feed = $this->getJson('/api/v1/public/stock-feed?since='.$cursor)->assertOk();
        $this->assertSame($product->id, $feed->json('items.0.id'));
        $this->assertSame('low', $feed->json('items.0.state')); // sisa 4 <= ambang 5
        $this->assertSame('4', $feed->json('items.0.qty'));
        $this->assertStringNotContainsString('cost', $feed->getContent());
    }

    public function test_sitemap_and_robots(): void
    {
        $product = $this->stockedProduct('5', 1000);

        $this->get('/sitemap.xml')->assertOk()->assertSee($product->url(), false);
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin', false)->assertSee('Sitemap:', false);
    }
}
