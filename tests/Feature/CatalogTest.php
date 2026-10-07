<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Etalase;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
        Storage::fake('public');
    }

    /** Produk dengan tiga variasi: [kecil: ada stok, sedang: ada stok, besar: habis]. */
    private function kopi(): ProductGroup
    {
        $category = Category::create(['name' => 'Minuman', 'slug' => 'minuman']);
        $group = ProductGroup::create([
            'category_id' => $category->id, 'name' => 'Kopi Robusta', 'slug' => 'kopi-robusta', 'brand' => 'Pandanaran',
            'summary' => 'Sangrai medium.', 'description' => "Paragraf satu.\n\nParagraf dua.",
            'highlights' => ['Wangi'], 'specs' => [['label' => 'Asal', 'value' => 'Temanggung']],
            'option_names' => ['Berat'], 'is_active' => true, 'is_online' => true, 'is_featured' => true,
        ]);

        foreach ([['KP-100', '100 g', 13000, '10'], ['KP-200', '200 g', 24000, '4'], ['KP-500', '500 g', 55000, '0']] as $i => [$sku, $name, $price, $qty]) {
            $p = Product::create([
                'group_id' => $group->id, 'category_id' => $category->id, 'sku' => $sku, 'name' => "Kopi Robusta {$name}",
                'slug' => Product::uniqueSlug("Kopi Robusta {$name}"), 'variant_name' => $name, 'options' => ['Berat' => $name],
                'sort_order' => $i, 'price' => $price, 'unit' => 'bungkus', 'min_stock' => 5, 'is_active' => true, 'is_online' => true,
            ]);
            if ((float) $qty > 0) {
                app(InventoryService::class)->receive($p, $qty, 9000, 'equity');
            }
        }

        return $group->fresh();
    }

    public function test_plain_product_gets_its_own_group_and_page(): void
    {
        $product = $this->stockedProduct('5', 1000, ['name' => 'Gula Pasir']);

        $this->assertNotNull($product->group_id);
        $group = $product->group;
        $this->assertTrue($group->auto);
        $this->assertSame('Gula Pasir', $group->name);
        $this->assertSame(10000, $group->price_min);

        $this->get($product->url())->assertOk()->assertSee('Gula Pasir');

        // Nama dan harga di produk ikut mengubah grup otomatis.
        $product->update(['name' => 'Gula Pasir Halus', 'price' => 12000]);
        $this->assertSame('Gula Pasir Halus', $group->fresh()->name);
        $this->assertSame(12000, $group->fresh()->price_min);
    }

    public function test_product_page_lists_every_variant_with_sku_and_price(): void
    {
        $group = $this->kopi();

        $html = $this->get($group->url())->assertOk()
            ->assertSee('Kopi Robusta')->assertSee('KP-100')->assertSee('KP-200')->assertSee('KP-500')
            ->assertSee('Rp13.000', false)->assertSee('Rp55.000', false)
            ->assertSee('Semua variasi dan harga')->assertSee('Temanggung')->assertSee('Paragraf dua.')
            ->getContent();

        $this->assertStringContainsString('"@type":"ProductGroup"', $html);
        $this->assertStringContainsString('"hasVariant"', $html);
        $this->assertStringContainsString('id="pdp-data"', $html);
        // Variasi pertama yang ada stok jadi pilihan awal, tombol beli aktif.
        $this->assertMatchesRegularExpression('/data-buy="\d+" data-buy-btn >Tambah ke keranjang/', $html);
    }

    public function test_group_stock_summary_follows_its_variants(): void
    {
        $group = $this->kopi();
        $this->assertSame('ok', $group->stockState());
        $this->assertSame('Stok tersedia', $group->publicStockLabel());

        // Satu-satunya variasi yang tersisa menipis -> label gabungan "terbatas".
        $group->variants->firstWhere('sku', 'KP-100')->update(['is_online' => false]);
        $group = $group->fresh();
        $this->assertSame('low', $group->stockState());
        $this->assertSame('Stok terbatas', $group->publicStockLabel());

        $group->variants->firstWhere('sku', 'KP-200')->update(['is_online' => false]);
        $this->assertSame('out', $group->fresh()->stockState());
    }

    public function test_price_range_and_card_hint(): void
    {
        $group = $this->kopi();
        $this->assertSame(13000, $group->price_min);
        $this->assertSame(55000, $group->price_max);

        $this->get(route('shop.index'))->assertOk()->assertSee('3 variasi')->assertSee('mulai')->assertSee('Pilih variasi')
            ->assertSee('data-stock-group', false);
    }

    public function test_variant_is_added_to_cart_by_its_own_slug(): void
    {
        $group = $this->kopi();
        $variant = $group->variants->firstWhere('sku', 'KP-200');

        $this->post(route('cart.add', $variant->slug), ['qty' => 2])->assertRedirect();
        $this->get(route('cart.index'))->assertOk()->assertSee('Kopi Robusta 200 g');
    }

    public function test_search_finds_a_product_by_variant_sku(): void
    {
        $this->kopi();
        $this->stockedProduct('5', 1000, ['name' => 'Garam Halus']);

        $this->get(route('shop.index', ['q' => 'KP-500']))->assertOk()->assertSee('Kopi Robusta')->assertDontSee('Garam Halus');
    }

    public function test_etalase_page_and_filter(): void
    {
        $group = $this->kopi();
        $this->stockedProduct('5', 1000, ['name' => 'Garam Halus']);
        $etalase = Etalase::create(['name' => 'Terlaris', 'slug' => 'terlaris']);
        $group->etalases()->attach($etalase);

        $this->get($etalase->url())->assertOk()->assertSee('Kopi Robusta')->assertDontSee('Garam Halus');
        $this->get(route('shop.index', ['etalase' => 'terlaris']))->assertOk()->assertSee('Kopi Robusta')->assertDontSee('Garam Halus');
        $this->get($group->url())->assertSee('Terlaris');

        $etalase->update(['is_visible' => false]);
        $this->get($etalase->url())->assertNotFound();
    }

    public function test_only_in_stock_filter(): void
    {
        $this->kopi();
        $empty = $this->makeProduct(['name' => 'Barang Kosong']);

        $this->get(route('shop.index', ['tersedia' => 1]))->assertOk()->assertSee('Kopi Robusta')->assertDontSee('Barang Kosong');
        $this->get(route('shop.index'))->assertSee('Barang Kosong');
    }

    public function test_group_without_sellable_variant_is_hidden(): void
    {
        $group = $this->kopi();
        $group->variants()->update(['is_online' => false]);

        $this->get($group->url())->assertNotFound();
        $this->get(route('shop.index'))->assertDontSee('Kopi Robusta');
    }

    public function test_sitemap_lists_groups_and_etalases(): void
    {
        $group = $this->kopi();
        $etalase = Etalase::create(['name' => 'Terlaris', 'slug' => 'terlaris']);
        $group->etalases()->attach($etalase);

        $this->get('/sitemap.xml')->assertOk()->assertSee($group->url(), false)->assertSee($etalase->url(), false);
    }

    private function png(): UploadedFile
    {
        $img = imagecreatetruecolor(1600, 900);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 60, 40));
        $path = tempnam(sys_get_temp_dir(), 'img').'.png';
        imagepng($img, $path);

        return new UploadedFile($path, 'foto.png', 'image/png', null, true);
    }

    public function test_admin_builds_a_product_with_variants_and_photos(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $category = Category::create(['name' => 'Sembako', 'slug' => 'sembako']);
        $etalase = Etalase::create(['name' => 'Promo', 'slug' => 'promo']);

        $this->post(route('admin.catalog.store'), [
            'name' => 'Beras Premium', 'brand' => 'Azola', 'category_id' => $category->id, 'option_names' => 'Kemasan',
            'summary' => 'Pulen.', 'highlights' => "Utuh\nBersih", 'specs' => "Asal: Jawa\nKadar air: 14%",
            'etalases' => [$etalase->id], 'is_active' => 1, 'is_online' => 1,
        ])->assertRedirect();

        $group = ProductGroup::where('name', 'Beras Premium')->firstOrFail();
        $this->assertFalse($group->auto);
        $this->assertSame(['Utuh', 'Bersih'], $group->highlights);
        $this->assertSame('Kadar air', $group->specs[1]['label']);
        $this->assertSame(['Kemasan'], $group->option_names);
        $this->assertTrue($group->etalases->contains($etalase));

        $this->post(route('admin.catalog.variants.store', $group), [
            'variant_name' => '5 kg', 'sku' => 'BRS-5', 'unit' => 'karung', 'price' => 78000,
            'options' => ['Kemasan' => '5 kg'], 'initial_qty' => '10', 'initial_cost' => 68000,
        ])->assertRedirect();
        $this->post(route('admin.catalog.variants.store', $group), [
            'variant_name' => '10 kg', 'sku' => 'BRS-10', 'unit' => 'karung', 'price' => 152000, 'options' => ['Kemasan' => '10 kg'],
        ])->assertRedirect();

        $five = Product::where('sku', 'BRS-5')->firstOrFail();
        $this->assertSame($group->id, $five->group_id);
        $this->assertSame('Beras Premium 5 kg', $five->name);
        $this->assertSame(['Kemasan' => '5 kg'], $five->options);
        $this->assertSame(1, StockMovement::where('product_id', $five->id)->count(), 'Stok awal lewat jalur resmi.');
        $this->assertSame(78000, $group->fresh()->price_min);
        $this->assertSame(152000, $group->fresh()->price_max);

        // Foto: tiga ukuran dibuat, yang pertama jadi sampul.
        $this->post(route('admin.catalog.images.store', $group), ['photos' => [$this->png(), $this->png()]])->assertRedirect();
        $group = $group->fresh(['images']);
        $this->assertCount(2, $group->images);
        $first = $group->images->first();
        foreach ([$first->path, $first->card_path, $first->thumb_path] as $file) {
            Storage::disk('public')->assertExists($file);
        }
        $card = getimagesizefromstring(Storage::disk('public')->get($first->card_path));
        $large = getimagesizefromstring(Storage::disk('public')->get($first->path));
        $this->assertSame([640, 640], [$card[0], $card[1]], 'Kartu persegi 640 px.');
        $this->assertSame(1400, $large[0], 'Gambar besar dibatasi 1400 px, rasio asli.');
        $this->assertSame(788, $large[1]);

        // Halaman toko memakai ukuran yang tepat: kartu untuk daftar, besar untuk detail.
        $this->get(route('shop.index'))->assertOk()->assertSee($first->card_path, false);
        $this->get($group->url())->assertOk()->assertSee($first->path, false)->assertSee($first->thumb_path, false);

        // Jadikan foto kedua sampul, lalu hapus: file ikut terhapus.
        $second = $group->images->last();
        $this->post(route('admin.catalog.images.move', [$group, $second]), ['to' => 'first'])->assertRedirect();
        $this->assertSame($second->id, $group->fresh()->images->first()->id);

        $this->delete(route('admin.catalog.images.destroy', [$group, $second]))->assertRedirect();
        Storage::disk('public')->assertMissing($second->card_path);
    }

    public function test_admin_rejects_duplicate_sku_and_non_image_upload(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $group = $this->kopi();

        $this->post(route('admin.catalog.variants.store', $group), [
            'variant_name' => 'Dobel', 'sku' => 'KP-100', 'unit' => 'bungkus', 'price' => 1000,
        ])->assertSessionHasErrors('sku');

        $this->post(route('admin.catalog.images.store', $group), ['photos' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0');
    }

    public function test_catalog_admin_requires_admin(): void
    {
        $this->get(route('admin.catalog.index'))->assertRedirect();
        $this->actingAs($this->makeUser('cashier'))->get(route('admin.catalog.index'))->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $group = $this->kopi();
        Etalase::create(['name' => 'Terlaris', 'slug' => 'terlaris']);

        $this->get(route('admin.catalog.index'))->assertOk()->assertSee('Kopi Robusta');
        $this->get(route('admin.catalog.create'))->assertOk();
        $this->get(route('admin.catalog.edit', $group))->assertOk()->assertSee('KP-200')->assertSee('Tambah variasi');
        $this->get(route('admin.etalases.index'))->assertOk()->assertSee('Terlaris');
        $this->post(route('admin.etalases.store'), ['name' => 'Promo minggu ini'])->assertRedirect();
        $this->assertDatabaseHas('etalases', ['slug' => 'promo-minggu-ini']);
    }

    public function test_pos_api_still_exposes_variant_skus(): void
    {
        $group = $this->kopi();
        $this->assertSame(3, Product::where('group_id', $group->id)->count());
        $presented = \App\Support\Presenter::product(Product::where('sku', 'KP-200')->firstOrFail());
        $this->assertSame('Kopi Robusta 200 g', $presented['name']);
        $this->assertSame(24000, $presented['price']);
    }
}
