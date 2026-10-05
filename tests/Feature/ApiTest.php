<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
    }

    /** @return array{0: array<string,string>, 1: User} */
    private function asDevice(string $role = 'cashier', string $type = 'desktop'): array
    {
        $user = $this->makeUser($role);
        [, $plain] = ApiToken::issue($user, 'Perangkat uji', $type);

        return [['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'], $user];
    }

    public function test_requests_without_token_are_rejected(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $this->getJson('/api/v1/products', ['Authorization' => 'Bearer salah'])->assertUnauthorized();
    }

    public function test_login_returns_token_and_wrong_password_is_rejected(): void
    {
        $user = $this->makeUser('cashier');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'bukan-password', 'device_name' => 'Kasir 1', 'device_type' => 'desktop',
        ])->assertStatus(422);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'Kasir 1', 'device_type' => 'android',
        ])->assertCreated()->assertJsonPath('channel', 'pos_android');

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$response->json('token')])
            ->assertOk()->assertJsonPath('user.role', 'cashier');
    }

    public function test_pos_sale_is_idempotent_and_records_channel(): void
    {
        [$headers] = $this->asDevice('cashier', 'android');
        $product = $this->stockedProduct('10', 6000, ['price' => 10000]);
        $payload = [
            'uuid' => '0b5a0c12-3d1e-4f55-9a77-5f2b8c1d9e01',
            'items' => [['sku' => $product->sku, 'qty' => 2]],
            'payment_method' => 'cash',
        ];

        $this->postJson('/api/v1/orders', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('data.channel', 'pos_android')
            ->assertJsonPath('data.grand_total', 20000);

        $this->postJson('/api/v1/orders', $payload, $headers)
            ->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, Order::count());
        $this->assertSame(8000, $product->fresh()->qtyMilli());
    }

    public function test_client_cannot_override_price(): void
    {
        [$headers] = $this->asDevice();
        $product = $this->stockedProduct('10', 1000, ['price' => 10000]);

        $this->postJson('/api/v1/orders', [
            'uuid' => '9d3c1f4a-77b2-4c0e-8a11-2e5d6f7a8b90',
            'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 1]],
        ], $headers)->assertCreated()->assertJsonPath('data.grand_total', 10000);
    }

    public function test_insufficient_stock_returns_422_and_changes_nothing(): void
    {
        [$headers] = $this->asDevice();
        $product = $this->stockedProduct('2', 1000);

        $this->postJson('/api/v1/orders', [
            'uuid' => '3a8b1c2d-4e5f-4a6b-8c7d-9e0f1a2b3c4d',
            'items' => [['product_id' => $product->id, 'qty' => 5]],
        ], $headers)->assertStatus(422)->assertJsonPath('stock_conflict.available', '2.000');

        $this->assertSame(0, Order::count());
        $this->assertSame(2000, $product->fresh()->qtyMilli());
    }

    public function test_stock_feed_returns_snapshot_then_only_changes(): void
    {
        [$headers] = $this->asDevice();
        $sold = $this->stockedProduct('10', 1000);
        $untouched = $this->stockedProduct('7', 1000);

        $snapshot = $this->getJson('/api/v1/sync/stock', $headers)->assertOk();
        $this->assertTrue($snapshot->json('full'));
        $this->assertCount(2, $snapshot->json('items'));
        $cursor = $snapshot->json('cursor');

        $this->postJson('/api/v1/orders', [
            'uuid' => '5c6d7e8f-9a0b-4c1d-8e2f-3a4b5c6d7e8f',
            'items' => [['product_id' => $sold->id, 'qty' => 3]],
        ], $headers)->assertCreated();

        $delta = $this->getJson('/api/v1/sync/stock?since='.$cursor, $headers)->assertOk();
        $this->assertFalse($delta->json('full'));
        $this->assertCount(1, $delta->json('items'));
        $this->assertSame($sold->id, $delta->json('items.0.id'));
        $this->assertSame('7.000', $delta->json('items.0.stock'));
        $this->assertGreaterThan($cursor, $delta->json('cursor'));

        $this->assertCount(0, $this->getJson('/api/v1/sync/stock?since='.$delta->json('cursor'), $headers)->json('items'));
        $this->assertSame(7000, $untouched->fresh()->qtyMilli());
    }

    public function test_cashier_cannot_use_admin_endpoints_but_admin_can(): void
    {
        [$cashier] = $this->asDevice('cashier');
        [$admin] = $this->asDevice('admin');
        $product = $this->makeProduct();

        $this->getJson('/api/v1/reconciliation', $cashier)->assertForbidden();
        $this->postJson('/api/v1/stock/receive', ['product_id' => $product->id, 'qty' => 5, 'unit_cost' => 1000], $cashier)->assertForbidden();

        $this->postJson('/api/v1/stock/receive', ['product_id' => $product->id, 'qty' => 5, 'unit_cost' => 1000, 'funding' => 'cash'], $admin)
            ->assertCreated()->assertJsonPath('data.stock', '5.000')->assertJsonPath('data.cost', 1000);

        $this->getJson('/api/v1/reconciliation', $admin)->assertOk()->assertJsonPath('ok', true);
        $this->getJson('/api/v1/journals', $admin)->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_admin_can_cancel_and_stock_returns(): void
    {
        [$admin] = $this->asDevice('admin');
        $product = $this->stockedProduct('10', 1000);

        $uuid = '7e8f9a0b-1c2d-4e3f-9a4b-5c6d7e8f9a0b';
        $this->postJson('/api/v1/orders', ['uuid' => $uuid, 'items' => [['product_id' => $product->id, 'qty' => 4]]], $admin)->assertCreated();
        $this->postJson("/api/v1/orders/{$uuid}/cancel", [], $admin)->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(10000, $product->fresh()->qtyMilli());
        $this->getJson('/api/v1/reconciliation', $admin)->assertJsonPath('ok', true);
    }

    public function test_revoked_token_stops_working(): void
    {
        [$headers] = $this->asDevice();

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
    }
}
