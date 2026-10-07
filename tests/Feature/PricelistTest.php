<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\SimplePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class PricelistTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
    }

    /** Ambil semua teks dari isi PDF (aliran Flate dibuka dulu). */
    private function pdfText(string $pdf): string
    {
        preg_match_all('/stream\n(.*?)\nendstream/s', $pdf, $m);
        $text = '';
        foreach ($m[1] as $stream) {
            $raw = @gzuncompress($stream);
            $text .= $raw === false ? $stream : $raw;
        }

        return $text;
    }

    public function test_page_lists_skus_grouped_by_category_with_download_link(): void
    {
        $cat = Category::create(['name' => 'Minuman', 'slug' => 'minuman']);
        $this->stockedProduct('5', 1000, ['name' => 'Kopi Gayo', 'sku' => 'KG-1', 'price' => 24000, 'category_id' => $cat->id]);
        $this->makeProduct(['name' => 'Teh Habis', 'sku' => 'TH-1', 'price' => 5000, 'category_id' => $cat->id]);

        $this->get(route('pricelist'))->assertOk()
            ->assertSee('Minuman')->assertSee('Kopi Gayo')->assertSee('KG-1')->assertSee('Rp24.000', false)
            ->assertSee('Habis')->assertSee('Unduh PDF')->assertSee(route('pricelist.pdf'), false);
    }

    public function test_filters_apply_to_page_and_pdf(): void
    {
        $this->stockedProduct('5', 1000, ['name' => 'Kopi Gayo', 'sku' => 'KG-1']);
        $this->makeProduct(['name' => 'Teh Habis', 'sku' => 'TH-1']);

        $this->get(route('pricelist', ['tersedia' => 1]))->assertOk()->assertSee('Kopi Gayo')->assertDontSee('Teh Habis');
        $this->get(route('pricelist', ['q' => 'TH-1']))->assertOk()->assertSee('Teh Habis')->assertDontSee('Kopi Gayo');

        $pdf = $this->get(route('pricelist.pdf', ['tersedia' => 1]))->assertOk()->getContent();
        $text = $this->pdfText($pdf);
        $this->assertStringContainsString('Kopi Gayo', $text);
        $this->assertStringNotContainsString('Teh Habis', $text);
    }

    public function test_pdf_is_a_valid_download_with_names_prices_and_skus(): void
    {
        $this->stockedProduct('5', 1000, ['name' => 'Kopi (Gayo) & Gula', 'sku' => 'KG-1', 'price' => 24000]);

        $response = $this->get(route('pricelist.pdf'))->assertOk();
        $pdf = $response->getContent();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="pricelist-', $response->headers->get('Content-Disposition'));

        $text = $this->pdfText($pdf);
        $this->assertStringContainsString('Kopi \\(Gayo\\) & Gula', $text, 'Tanda kurung di-escape.');
        $this->assertStringContainsString('KG-1', $text);
        $this->assertStringContainsString('Rp24.000', $text);
        $this->assertStringContainsString('Halaman 1 dari 1', $text);

        // xref harus menunjuk tepat ke tiap objek.
        preg_match('/startxref\n(\d+)\n/', $pdf, $m);
        $this->assertStringStartsWith('xref', substr($pdf, (int) $m[1], 4));
    }

    public function test_long_lists_flow_onto_more_pages_with_numbering(): void
    {
        for ($i = 1; $i <= 90; $i++) {
            $this->makeProduct(['name' => sprintf('Barang %03d', $i), 'sku' => sprintf('B-%03d', $i)]);
        }

        $pdf = $this->get(route('pricelist.pdf'))->assertOk()->getContent();
        preg_match('/\/Type \/Pages \/Count (\d+)/', $pdf, $m);
        $pages = (int) $m[1];

        $this->assertGreaterThanOrEqual(3, $pages);
        $text = $this->pdfText($pdf);
        $this->assertStringContainsString("Halaman {$pages} dari {$pages}", $text);
        $this->assertStringContainsString('Barang 001', $text);
        $this->assertStringContainsString('Barang 090', $text);
        $this->assertStringContainsString('\\(lanjutan\\)', $text);
    }

    public function test_empty_filter_still_returns_a_pdf(): void
    {
        $pdf = $this->get(route('pricelist.pdf', ['q' => 'tidak-ada']))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('Belum ada produk yang cocok', $this->pdfText($pdf));
    }

    public function test_pdf_helper_measures_and_fits_text(): void
    {
        $pdf = new SimplePdf;
        // "Hello" Helvetica = 722+556+222+222+556 = 2278 / 1000 * 10
        $this->assertEqualsWithDelta(22.78, $pdf->width('Hello', 10), 0.01);
        $this->assertLessThanOrEqual(60, $pdf->width($pdf->fit('Teks yang sangat panjang sekali', 60, 10), 10));
        $this->assertStringEndsWith('...', $pdf->fit('Teks yang sangat panjang sekali', 60, 10));
    }

    public function test_pricelist_in_sitemap_and_menu(): void
    {
        $this->get('/sitemap.xml')->assertSee(route('pricelist'), false);
        $this->get('/')->assertSee(route('pricelist'), false);
    }
}
