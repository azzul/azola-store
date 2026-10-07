<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
    }

    private function register(array $over = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('account.register.store'), $over + [
            'name' => 'Sari Wulandari', 'email' => 'sari@example.test', 'phone' => '081234567890',
            'password' => 'rahasia-banget', 'password_confirmation' => 'rahasia-banget',
        ]);
    }

    public function test_customer_can_register_login_and_logout(): void
    {
        $this->register()->assertRedirect(route('account.home'));

        $user = User::where('email', 'sari@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertAuthenticatedAs($user);
        $this->get(route('account.home'))->assertOk()->assertSee('Halo, Sari');

        $this->post(route('account.logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        $this->post(route('account.login.store'), ['email' => 'sari@example.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->post(route('account.login.store'), ['email' => 'sari@example.test', 'password' => 'rahasia-banget'])->assertRedirect(route('account.home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_validation_and_honeypot(): void
    {
        $this->register(['password_confirmation' => 'beda'])->assertSessionHasErrors('password');
        $this->register(['email' => 'bukan-email'])->assertSessionHasErrors('email');
        $this->register(['website' => 'bot'])->assertRedirect(route('home'));
        $this->assertSame(0, User::where('role', User::ROLE_CUSTOMER)->count());

        $this->register()->assertRedirect();
        auth()->logout();
        $this->register()->assertSessionHasErrors('email'); // email sama
    }

    public function test_guests_are_sent_to_customer_login_and_admin_area_to_admin_login(): void
    {
        $this->get(route('account.orders'))->assertRedirect(route('account.login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_open_admin_or_use_pos_api(): void
    {
        $this->register();
        $this->get(route('admin.dashboard'))->assertForbidden();

        auth()->logout();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'sari@example.test', 'password' => 'rahasia-banget', 'device_name' => 'HP', 'device_type' => 'android',
        ])->assertStatus(422);
    }

    public function test_checkout_links_order_to_logged_in_customer_and_lists_it(): void
    {
        $product = $this->stockedProduct('10', 6000, ['name' => 'Kopi Gayo']);
        $this->register();
        $user = User::where('email', 'sari@example.test')->firstOrFail();
        $user->forceFill(['address' => 'Jl. Melati 5, Semarang'])->save();
        $this->actingAs($user->fresh()); // guard menyimpan instance lama dari proses daftar

        $this->post(route('cart.add', $product->slug), ['qty' => 2]);
        $this->get(route('checkout.show'))->assertOk()->assertSee('Sari Wulandari', false)->assertSee('Jl. Melati 5, Semarang', false);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Sari Wulandari', 'customer_phone' => '081234567890', 'delivery_method' => 'pickup', 'payment_method' => 'transfer',
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->customer_id);
        $this->get(route('account.orders'))->assertOk()->assertSee($order->number);

        // Pembeli lain tidak melihat pesanan ini.
        auth()->logout();
        $this->register(['email' => 'budi@example.test', 'name' => 'Budi']);
        $this->get(route('account.orders'))->assertOk()->assertDontSee($order->number);
    }

    public function test_guest_checkout_still_works_without_account(): void
    {
        $product = $this->stockedProduct('5', 6000);
        $this->post(route('cart.add', $product->slug), ['qty' => 1]);
        $this->post(route('checkout.store'), [
            'customer_name' => 'Tamu', 'customer_phone' => '0812345678', 'delivery_method' => 'pickup', 'payment_method' => 'cod',
        ])->assertRedirect();

        $this->assertNull(Order::firstOrFail()->customer_id);
    }

    public function test_profile_and_password_update(): void
    {
        $this->register();
        $this->put(route('account.profile.update'), ['name' => 'Sari W', 'phone' => '0899999999', 'address' => 'Jl. Baru 1'])->assertSessionHasNoErrors();
        $this->assertSame('Jl. Baru 1', User::where('email', 'sari@example.test')->first()->address);

        $this->put(route('account.password'), ['current_password' => 'salah', 'password' => 'baru-12345', 'password_confirmation' => 'baru-12345'])->assertSessionHasErrors('current_password');
        $this->put(route('account.password'), ['current_password' => 'rahasia-banget', 'password' => 'baru-12345', 'password_confirmation' => 'baru-12345'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->post(route('account.login.store'), ['email' => 'sari@example.test', 'password' => 'baru-12345'])->assertRedirect(route('account.home'));
    }

    public function test_header_shows_icons_bottom_nav_and_whatsapp_button(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        foreach (['class="logo"', 'class="tools"', 'class="bnav"', 'class="wafab"', 'Pesanan saya', 'favicon.svg'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringContainsString('wa.me/6281234567890', $html);
        $this->assertStringContainsString('instagram.com/azolastore', $html);
    }
}
