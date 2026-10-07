<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ContactMessage;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
    }

    public function test_all_profile_pages_render_with_one_h1_and_canonical(): void
    {
        foreach (['about', 'contact', 'reviews', 'clients', 'faq', 'privacy', 'terms', 'returns'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<h1'), "{$route}: satu h1");
            $this->assertStringContainsString('rel="canonical"', $html);
        }
    }

    public function test_faq_page_has_faq_schema_and_empty_pages_are_noindex(): void
    {
        $this->get(route('faq'))->assertSee('"@type":"FAQPage"', false);
        $this->get(route('reviews'))->assertSee('noindex,follow', false);
        $this->get(route('clients'))->assertSee('noindex,follow', false);
    }

    public function test_contact_form_saves_message_and_blocks_bots(): void
    {
        $this->post(route('contact.send'), ['name' => 'Rina', 'phone' => '08123456789', 'message' => 'Apakah gula pasir ready?'])
            ->assertRedirect(route('contact'))->assertSessionHas('sent');
        $this->assertSame(1, ContactMessage::count());

        $this->post(route('contact.send'), ['name' => 'Bot', 'phone' => '0812', 'message' => 'spam spam spam', 'website' => 'x']);
        $this->assertSame(1, ContactMessage::count());

        $this->post(route('contact.send'), ['name' => 'Tanpa kontak', 'message' => 'pesan tanpa telepon atau email'])
            ->assertSessionHasErrors(['phone', 'email']);
    }

    public function test_customer_review_waits_for_approval_then_shows_with_rating_schema(): void
    {
        $this->post(route('reviews.store'), ['name' => 'Sari', 'rating' => 5, 'body' => 'Barang sesuai dan cepat sampai.'])
            ->assertRedirect(route('reviews'));

        $review = Testimonial::firstOrFail();
        $this->assertFalse($review->is_published);
        $this->get(route('reviews'))->assertDontSee('Barang sesuai');
        $this->get('/')->assertDontSee('Kata pelanggan');

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->post(route('admin.reviews.toggle', $review))->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('reviews'))->assertSee('Barang sesuai')->assertSee('"@type":"AggregateRating"', false)->assertDontSee('noindex', false);
        $this->get('/')->assertSee('Kata pelanggan')->assertSee('Barang sesuai');
    }

    public function test_home_has_rich_sections_and_no_fake_content(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['data-hero', 'id="kenapa-kami"', 'id="cara-belanja"', 'data-sync', 'id="faq"', 'class="visit"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertSame(1, substr_count($html, '<h1'), 'Satu h1 per halaman.');
        $this->assertSame(count(config('store.why')), substr_count($html, 'class="why__card"'));
        // Tanpa ulasan/klien sungguhan: tampil ajakan menulis ulasan, bagian klien disembunyikan.
        $this->assertStringContainsString('Tulis ulasan', $html);
        $this->assertStringNotContainsString('Kata pelanggan', $html);
        $this->assertStringNotContainsString('Dipercaya oleh', $html);
        $this->assertStringContainsString('css/home.css', $html);
        $this->assertStringContainsString('js/home.js', $html);
    }

    public function test_review_validation_and_honeypot(): void
    {
        $this->post(route('reviews.store'), ['name' => 'A', 'rating' => 9, 'body' => 'pendek'])->assertSessionHasErrors(['rating', 'body']);
        $this->post(route('reviews.store'), ['name' => 'Bot', 'rating' => 5, 'body' => 'ulasan palsu dari bot', 'website' => 'x']);
        $this->assertSame(0, Testimonial::count());
    }

    public function test_admin_manages_clients_reviews_and_messages(): void
    {
        Storage::fake('public');
        $this->actingAs($this->makeUser('admin'));

        $this->post(route('admin.clients.store'), ['name' => 'CV Maju', 'url' => 'https://maju.example', 'logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHasNoErrors();
        $client = Client::firstOrFail();
        Storage::disk('public')->assertExists($client->logo_path);

        $this->get(route('clients'))->assertSee('CV Maju')->assertDontSee('noindex', false);
        $this->get('/sitemap.xml')->assertSee(route('clients'), false);

        $this->post(route('admin.clients.toggle', $client));
        $this->get(route('clients'))->assertDontSee('CV Maju');

        $this->post(route('admin.reviews.store'), ['name' => 'Pak Budi', 'rating' => 4, 'body' => 'Pelayanan ramah dan harga sama dengan di toko.'])->assertSessionHasNoErrors();
        $this->assertTrue(Testimonial::firstOrFail()->is_published);

        $message = ContactMessage::create(['name' => 'Dewi', 'phone' => '0812', 'message' => 'Halo, tanya ongkir ke Ungaran']);
        $this->get(route('admin.messages.index'))->assertOk()->assertSee('Dewi');
        $this->get(route('admin.messages.show', $message))->assertOk();
        $this->assertNotNull($message->fresh()->read_at);

        foreach (['admin.reviews.index', 'admin.clients.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_sitemap_lists_profile_pages(): void
    {
        $this->get('/sitemap.xml')->assertSee(route('about'), false)->assertSee(route('contact'), false)->assertDontSee(route('reviews'), false);
    }
}
