<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\StoreSettings;
use Database\Seeders\DemoSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
        Storage::fake('public');
    }

    private function admin(): static
    {
        return $this->actingAs($this->makeUser('admin'));
    }

    private function save(array $o = [])
    {
        return $this->admin()->put(route('admin.settings.update'), $o);
    }

    public function test_page_is_staff_only(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect();
        $this->actingAs($this->makeUser('customer'))->get(route('admin.settings.index'))->assertForbidden();
        $this->admin()->get(route('admin.settings.index'))->assertOk()->assertSee('Pengaturan toko');
    }

    public function test_saved_values_override_config_and_show_on_site(): void
    {
        $this->save([
            'name' => 'Toko Uji', 'address' => 'Jl. Uji No. 1, Semarang', 'whatsapp' => '+62 812-9999-0000',
            'instagram' => '@tokouji', 'hours' => "Senin | 09.00 - 15.00\nMinggu | Tutup",
        ])->assertSessionHasNoErrors();

        StoreSettings::apply();
        $this->assertSame('Toko Uji', config('store.name'));
        $this->assertSame('Jl. Uji No. 1, Semarang', config('store.address'));
        $this->assertSame('6281299990000', config('store.whatsapp'));
        $this->assertSame('tokouji', config('store.instagram'));
        $this->assertSame(['Senin', '09.00 - 15.00'], array_values((array) config('store.hours')[0]));

        $this->get('/kontak')->assertSee('Jl. Uji No. 1, Semarang');
    }

    public function test_empty_value_falls_back_to_default(): void
    {
        $this->save(['address' => 'Alamat sementara'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', ['key' => 'address']);

        $this->save(['address' => ''])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('settings', ['key' => 'address']);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->save(['email' => 'bukan-email', 'brand' => 'hijau', 'map_url' => 'javascript:alert(1)', 'since' => '16'])
            ->assertSessionHasErrors(['email', 'brand', 'map_url', 'since']);
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_logo_upload_and_removal(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])->assertSessionHasNoErrors();
        $path = Setting::where('key', 'logo')->value('value');
        $this->assertStringStartsWith('storage/branding/', $path);
        Storage::disk('public')->assertExists(substr($path, strlen('storage/')));

        $this->save(['remove_logo' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('settings', ['key' => 'logo']);
        Storage::disk('public')->assertMissing(substr($path, strlen('storage/')));
    }

    public function test_demo_seeder_is_idempotent_and_keeps_admin_edits(): void
    {
        $this->seed(DemoSettingSeeder::class);
        Setting::where('key', 'address')->update(['value' => 'Alamat klien']);
        $count = Setting::count();

        $this->seed(DemoSettingSeeder::class);
        $this->assertSame($count, Setting::count());
        $this->assertSame('Alamat klien', Setting::where('key', 'address')->value('value'));
    }

    public function test_header_has_client_and_review_links(): void
    {
        $this->get('/')->assertSee(route('clients'), false)->assertSee(route('reviews'), false);
    }
}
