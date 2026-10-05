<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Journal;
use App\Models\Order;
use App\Models\Product;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
    }

    private function admin()
    {
        return $this->actingAs($this->makeUser('admin'));
    }

    private function webOrder(Product $product, int $qty = 2): Order
    {
        $this->post(route('cart.add', $product->slug), ['qty' => $qty]);
        $this->get('/checkout');
        $this->post('/checkout', [
            'customer_name' => 'Budi', 'customer_phone' => '08123456789',
            'delivery_method' => 'pickup', 'payment_method' => 'transfer',
        ]);

        return Order::firstOrFail();
    }

    public function test_guests_go_to_login_and_cashiers_are_refused(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/stok')->assertRedirect(route('admin.login'));

        $this->actingAs($this->makeUser('cashier'))->get('/admin')->assertForbidden();
        $this->get(route('admin.login'))->assertOk()->assertSee('Masuk');
    }

    public function test_admin_login_and_logout(): void
    {
        $user = $this->makeUser('admin');
        $user->forceFill(['password' => bcrypt('rahasia-123')])->save();

        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'rahasia-123'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_cashier_cannot_log_in_to_admin(): void
    {
        $user = $this->makeUser('cashier');
        $user->forceFill(['password' => bcrypt('rahasia-123')])->save();

        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'rahasia-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_all_admin_pages_render(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);
        $order = $this->webOrder($product);
        $journal = Journal::firstOrFail();

        $this->admin();
        foreach ([
            '/admin', '/admin/stok', '/admin/stok?tampil=low', '/admin/produk', '/admin/produk/baru',
            '/admin/produk/'.$product->id, '/admin/kategori', '/admin/pesanan', '/admin/pesanan/'.$order->id,
            '/admin/jurnal', '/admin/jurnal/'.$journal->id, '/admin/laporan', '/admin/rekonsiliasi', '/admin/perangkat',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_dashboard_and_stock_feed_follow_live_changes(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);
        $this->admin();

        $cursor = $this->getJson('/admin/stok/feed?since=0')->json('cursor');
        $this->assertGreaterThan(0, $cursor);

        $this->post(route('admin.products.adjust', $product), ['delta' => '-3', 'note' => 'rusak'])->assertSessionHasNoErrors();

        $feed = $this->getJson('/admin/stok/feed?since='.$cursor)->assertOk();
        $this->assertSame($product->id, $feed->json('items.0.id'));
        $this->assertSame('7', $feed->json('items.0.qty'));
    }

    public function test_product_crud_with_image_and_initial_stock(): void
    {
        Storage::fake('public');
        $this->admin();

        $this->post(route('admin.products.store'), [
            'name' => 'Sabun Cair', 'sku' => 'SBN-1', 'unit' => 'botol', 'price' => 12000,
            'is_active' => 1, 'is_online' => 1,
            'initial_qty' => '8', 'initial_cost' => 8000, 'funding' => 'equity',
            'image' => UploadedFile::fake()->image('sabun.jpg'),
        ])->assertSessionHasNoErrors();

        $product = Product::where('sku', 'SBN-1')->firstOrFail();
        $this->assertSame('sabun-cair', $product->slug);
        $this->assertSame(8000, $product->qtyMilli());
        $this->assertSame(8000, (int) $product->cost);
        Storage::disk('public')->assertExists($product->image_path);

        $this->put(route('admin.products.update', $product), [
            'name' => 'Sabun Cair 500 ml', 'sku' => 'SBN-1', 'unit' => 'botol', 'price' => 13500, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $fresh = $product->fresh();
        $this->assertSame(13500, $fresh->price);
        $this->assertFalse($fresh->is_online);
        $this->assertSame(8000, $fresh->qtyMilli(), 'Edit produk tidak boleh mengubah stok.');
        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_sku_must_be_unique(): void
    {
        $existing = $this->makeProduct(['sku' => 'DUP-1']);
        $this->admin();

        $this->post(route('admin.products.store'), ['name' => 'Lain', 'sku' => 'DUP-1', 'unit' => 'pcs', 'price' => 1000])
            ->assertSessionHasErrors('sku');
        $this->assertSame(1, Product::count());
        $this->assertNotNull($existing);
    }

    public function test_stock_receive_adjust_opname_keep_books_balanced(): void
    {
        $product = $this->stockedProduct('10', 6000);
        $this->admin();

        $this->post(route('admin.products.receive', $product), ['qty' => '5', 'unit_cost' => 9000, 'funding' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame(15000, $product->fresh()->qtyMilli());

        $this->post(route('admin.products.adjust', $product), ['delta' => '-2', 'note' => 'hilang'])->assertSessionHasNoErrors();
        $this->assertSame(13000, $product->fresh()->qtyMilli());

        $this->post(route('admin.products.opname', $product), ['counted' => '12'])->assertSessionHasNoErrors();
        $this->assertSame(12000, $product->fresh()->qtyMilli());

        // Tidak boleh minus lewat penyesuaian.
        $this->post(route('admin.products.adjust', $product), ['delta' => '-99', 'note' => 'salah'])->assertSessionHasErrors('delta');
        $this->assertSame(12000, $product->fresh()->qtyMilli());

        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_pay_then_cancel_web_order(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);
        $order = $this->webOrder($product, 3);
        $this->assertSame(7000, $product->fresh()->qtyMilli());

        $this->admin();
        $this->post(route('admin.orders.pay', $order), ['amount' => $order->grand_total, 'method' => 'transfer'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);

        // Bayar lebih dari sisa tagihan ditolak.
        $this->post(route('admin.orders.pay', $order), ['amount' => 1, 'method' => 'cash'])->assertSessionHasErrors('amount');

        $this->post(route('admin.orders.complete', $order))->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);

        $this->post(route('admin.orders.cancel', $order), ['reason' => 'tes'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10000, $product->fresh()->qtyMilli(), 'Stok kembali setelah batal.');
        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }

    public function test_device_token_is_shown_once_and_can_be_revoked(): void
    {
        $this->admin();

        $response = $this->post(route('admin.devices.store'), ['name' => 'Kasir depan', 'device_type' => 'desktop']);
        $response->assertRedirect(route('admin.devices.index'));
        $plain = session('new_token'); // flash: ada tepat setelah redirect

        $page = $this->get(route('admin.devices.index'))->assertOk();
        $this->assertStringStartsWith('azp_', (string) $plain);
        $page->assertSee($plain);

        // Muat ulang: token polos sudah hilang, hanya hash yang tersimpan.
        $this->get(route('admin.devices.index'))->assertDontSee($plain);
        $token = ApiToken::firstOrFail();
        $this->assertNotSame($plain, $token->token_hash);
        $this->assertNotNull(ApiToken::findByPlain($plain));

        $this->delete(route('admin.devices.revoke', $token))->assertSessionHasNoErrors();
        $this->assertNull(ApiToken::findByPlain($plain));
    }

    public function test_report_balances_after_sales(): void
    {
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);
        $order = $this->webOrder($product, 2);
        $this->admin();
        $this->post(route('admin.orders.pay', $order), ['amount' => $order->grand_total, 'method' => 'cash']);

        $this->get(route('admin.report', ['from' => now()->format('Y-m-d'), 'to' => now()->format('Y-m-d')]))
            ->assertOk()->assertSee('seimbang');
    }

    public function test_categories_can_be_added_and_removed_without_losing_products(): void
    {
        $this->admin();
        $this->post(route('admin.categories.store'), ['name' => 'Snack'])->assertSessionHasNoErrors();
        $category = \App\Models\Category::firstOrFail();
        $product = $this->makeProduct(['category_id' => $category->id]);

        $this->delete(route('admin.categories.destroy', $category))->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->category_id);
        $this->assertSame(1, Product::count());
    }
}
