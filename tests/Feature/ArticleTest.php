<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
        Storage::fake('public');
    }

    private function article(array $o = []): Article
    {
        static $n = 0;
        $n++;
        $title = $o['title'] ?? "Artikel Uji {$n}";

        return Article::create(array_merge([
            'title' => $title, 'slug' => Article::uniqueSlug($title), 'topic' => 'Tips', 'excerpt' => 'Ringkasan uji.',
            'body' => "## Bagian satu\n\nIsi **tebal** dengan [tautan](/produk).\n\n<script>alert(1)</script>",
            'author' => 'Tim', 'is_published' => true, 'published_at' => now()->subDay(),
        ], $o));
    }

    public function test_index_lists_only_published_articles(): void
    {
        $this->article(['title' => 'Terbit Satu']);
        $this->article(['title' => 'Masih Draf', 'is_published' => false]);
        $this->article(['title' => 'Terjadwal Nanti', 'published_at' => now()->addDays(3)]);

        $this->get(route('articles.index'))->assertOk()->assertSee('Terbit Satu')->assertDontSee('Masih Draf')->assertDontSee('Terjadwal Nanti');
    }

    public function test_detail_renders_markdown_safely_with_seo(): void
    {
        $a = $this->article(['title' => 'Cara Seduh Kopi']);

        $html = $this->get($a->url())->assertOk()->assertSee('Cara Seduh Kopi')->assertSee('<h2>Bagian satu</h2>', false)
            ->assertSee('<strong>tebal</strong>', false)->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html, 'HTML mentah dibuang.');
        $this->assertStringContainsString('"@type":"Article"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('rel="canonical" href="'.$a->url().'"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, $a->readingMinutes());
    }

    public function test_draft_and_scheduled_detail_are_not_found(): void
    {
        $this->get($this->article(['is_published' => false])->url())->assertNotFound();
        $this->get($this->article(['published_at' => now()->addDay()])->url())->assertNotFound();
    }

    public function test_topic_filter_and_sitemap(): void
    {
        $tips = $this->article(['title' => 'Tips A', 'topic' => 'Tips']);
        $this->article(['title' => 'Info B', 'topic' => 'Info toko']);

        $this->get(route('articles.index', ['topik' => 'Tips']))->assertOk()->assertSee('Tips A')->assertDontSee('Info B');
        $this->get('/sitemap.xml')->assertOk()->assertSee($tips->url(), false)->assertSee(route('articles.index'), false);
    }

    public function test_admin_writes_edits_and_deletes_an_article_with_cover(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $img = imagecreatetruecolor(1600, 900);
        $path = tempnam(sys_get_temp_dir(), 'cv').'.png';
        imagepng($img, $path);

        $this->post(route('admin.articles.store'), [
            'title' => 'Artikel Pertama', 'topic' => 'Tips', 'body' => 'Halo dunia.', 'is_published' => 1,
            'cover' => new UploadedFile($path, 'c.png', 'image/png', null, true),
        ])->assertRedirect();

        $a = Article::firstOrFail();
        $this->assertTrue($a->is_published);
        $this->assertSame('artikel-pertama', $a->slug);
        Storage::disk('public')->assertExists($a->cover_path);
        $card = getimagesizefromstring(Storage::disk('public')->get($a->cover_card_path));
        $this->assertSame([1000, 560], [$card[0], $card[1]]);

        $this->put(route('admin.articles.update', $a), ['title' => 'Artikel Pertama', 'body' => 'Diubah.', 'is_published' => 0])->assertRedirect();
        $this->assertFalse($a->fresh()->is_published);
        $this->get(route('articles.show', $a->slug))->assertNotFound();

        $this->get(route('admin.articles.index'))->assertOk()->assertSee('Artikel Pertama');
        $this->get(route('admin.articles.edit', $a))->assertOk();

        $this->delete(route('admin.articles.destroy', $a))->assertRedirect();
        $this->assertDatabaseCount('articles', 0);
        Storage::disk('public')->assertMissing($a->cover_path);
    }

    public function test_article_admin_is_admin_only(): void
    {
        $this->get(route('admin.articles.index'))->assertRedirect();
        $this->actingAs($this->makeUser('cashier'))->get(route('admin.articles.index'))->assertForbidden();
    }

    public function test_demo_seed_creates_articles(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->assertGreaterThanOrEqual(4, Article::published()->count());
        $this->get(route('articles.index'))->assertOk();
    }
}
