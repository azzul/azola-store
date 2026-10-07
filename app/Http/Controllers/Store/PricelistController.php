<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductGroup;
use App\Support\Rupiah;
use App\Support\Seo;
use App\Support\SimplePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Daftar harga: halaman web yang bisa disaring dan unduhan PDF dengan isi yang sama. */
class PricelistController extends Controller
{
    private const STATE = ['ok' => 'Tersedia', 'low' => 'Terbatas', 'out' => 'Habis'];

    public function index(Request $request)
    {
        $sections = $this->sections($request);
        $trail = [['Beranda', url('/')], ['Pricelist', route('pricelist')]];
        $filtered = $request->query('q') || $request->query('kategori') || $request->boolean('tersedia');

        return view('store.pricelist', [
            'seo' => Seo::page([
                'title' => 'Pricelist',
                'description' => 'Daftar harga lengkap '.config('store.name').' per variasi dan SKU, dengan status stok terbaru. Bisa diunduh sebagai PDF.',
                'canonical' => route('pricelist'),
                'robots' => $filtered ? 'noindex,follow' : 'index,follow',
                'jsonld' => [Seo::breadcrumbs($trail)],
            ]),
            'sections' => $sections,
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'q' => trim((string) $request->query('q', '')),
            'kategori' => (string) $request->query('kategori', ''),
            'onlyStock' => $request->boolean('tersedia'),
            'total' => $sections->sum(fn ($s) => $s['rows']->count()),
            'trail' => $trail,
            'query' => $request->only(['q', 'kategori', 'tersedia']),
        ]);
    }

    public function pdf(Request $request)
    {
        $sections = $this->sections($request);
        $pdf = $this->render($sections);
        $name = 'pricelist-'.Str::slug(config('store.name')).'-'.now()->format('Y-m-d').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * Baris per variasi (SKU), dikelompokkan per kategori.
     *
     * @return Collection<int, array{name: string, rows: Collection<int, array>}>
     */
    private function sections(Request $request): Collection
    {
        $q = trim((string) $request->query('q', ''));
        $slug = (string) $request->query('kategori', '');
        $onlyStock = $request->boolean('tersedia');

        $groups = ProductGroup::online()->with(['category', 'variants'])
            ->when($slug !== '', fn ($query) => $query->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('brand', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('name', 'like', $like)->orWhere('sku', 'like', $like)));
            })
            ->orderBy('name')->get();

        $rows = collect();
        foreach ($groups as $group) {
            foreach ($group->sellable() as $v) {
                if ($onlyStock && ! $v->isInStock()) {
                    continue;
                }
                $rows->push([
                    'category' => $group->category?->name ?? 'Lainnya',
                    'sort' => $group->category?->sort_order ?? 9999,
                    'product' => $group->name,
                    'variant' => $group->isSingle() ? null : ($v->variant_name ?: $v->name),
                    'url' => $group->url(),
                    'sku' => $v->sku,
                    'unit' => $v->unit,
                    'price' => (int) $v->price,
                    'state' => $v->stockState(),
                    'stock' => self::STATE[$v->stockState()],
                ]);
            }
        }

        return $rows->groupBy('category')
            ->map(fn ($items, $name) => ['name' => $name, 'sort' => $items->first()['sort'], 'rows' => $items->values()])
            ->sortBy([['sort', 'asc'], ['name', 'asc']])->values();
    }

    private function render(Collection $sections): string
    {
        $brand = $this->rgb(config('store.theme.brand', '#0F5C46'));
        $accent = $this->rgb(config('store.theme.accent', '#FFD43B'));
        $ink = [20, 35, 28];
        $muted = [102, 116, 108];
        $line = [217, 222, 213];
        $white = [255, 255, 255];
        $tint = [244, 247, 243];

        $pdf = new SimplePdf;
        $left = 40.0;
        $right = SimplePdf::W - 40;
        $store = config('store.name');
        $rowH = 19.0;
        $bottom = 800.0;
        $cols = ['name' => $left + 6, 'sku' => 290.0, 'unit' => 372.0, 'price' => 478.0, 'stock' => 492.0];

        $header = function (bool $first) use ($pdf, $brand, $accent, $white, $store, $left, $right): float {
            $pdf->addPage();
            if ($first) {
                $pdf->rect(0, 0, SimplePdf::W, 92, $brand);
                $pdf->rect(0, 92, SimplePdf::W, 4, $accent);
                $pdf->text($left, 46, $store, 22, true, $white);
                $pdf->text($left, 66, 'Daftar harga produk', 11, false, [214, 232, 224]);
                $pdf->text($right, 46, now()->translatedFormat('j F Y'), 10, true, $white, 'right');
                $pdf->text($right, 62, 'Harga dalam rupiah', 9, false, [214, 232, 224], 'right');

                return 120;
            }
            $pdf->rect(0, 0, SimplePdf::W, 34, $brand);
            $pdf->text($left, 22, $store, 11, true, $white);
            $pdf->text($right, 22, 'Daftar harga produk', 9, false, [214, 232, 224], 'right');

                return 56;
        };

        $y = $header(true);

        $columnHeads = function () use ($pdf, &$y, $cols, $muted, $line, $left, $right) {
            $pdf->text($cols['name'], $y, 'Produk', 8, true, $muted);
            $pdf->text($cols['sku'], $y, 'SKU', 8, true, $muted);
            $pdf->text($cols['unit'], $y, 'Satuan', 8, true, $muted);
            $pdf->text($cols['price'], $y, 'Harga', 8, true, $muted, 'right');
            $pdf->text($cols['stock'], $y, 'Stok', 8, true, $muted);
            $pdf->line($left, $y + 6, $right, $line, 0.8);
            $y += 8;
        };

        if ($sections->isEmpty()) {
            $pdf->text($left, $y + 20, 'Belum ada produk yang cocok dengan penyaringan ini.', 11, false, $muted);
        }

        foreach ($sections as $section) {
            // judul kategori butuh ruang untuk judul + minimal dua baris
            if ($y + 30 + $rowH * 2 > $bottom) {
                $y = $header(false);
            }
            $y += 8;
            $pdf->rect($left, $y, $right - $left, 22, $brand);
            $pdf->text($left + 8, $y + 15, $section['name'], 10.5, true, $white);
            $pdf->text($right - 8, $y + 15, $section['rows']->count().' SKU', 8.5, false, [214, 232, 224], 'right');
            $y += 22 + 14;
            $columnHeads();

            foreach ($section['rows'] as $i => $row) {
                if ($y + $rowH > $bottom) {
                    $y = $header(false);
                    $pdf->rect($left, $y - 14, $right - $left, 16, $tint);
                    $pdf->text($left + 6, $y - 3, $section['name'].' (lanjutan)', 8.5, true, $brand);
                    $y += 14;
                    $columnHeads();
                }

                if ($i % 2 === 1) {
                    $pdf->rect($left, $y - 1, $right - $left, $rowH, $tint);
                }
                $base = $y + 12;
                $label = $row['product'];
                if ($row['variant']) {
                    $variantW = $pdf->width('  '.$row['variant'], 9, false);
                    $label = $pdf->fit($row['product'], $cols['sku'] - $cols['name'] - 10 - min($variantW, 110), 9, true);
                    $pdf->text($cols['name'], $base, $label, 9, true, $ink);
                    $pdf->text($cols['name'] + $pdf->width($label.'  ', 9, true), $base, $pdf->fit($row['variant'], $cols['sku'] - $cols['name'] - $pdf->width($label.'  ', 9, true) - 8, 9), 9, false, $muted);
                } else {
                    $pdf->text($cols['name'], $base, $pdf->fit($label, $cols['sku'] - $cols['name'] - 8, 9, true), 9, true, $ink);
                }
                $pdf->text($cols['sku'], $base, $pdf->fit($row['sku'], 78, 8.5), 8.5, false, $muted);
                $pdf->text($cols['unit'], $base, $pdf->fit($row['unit'], 56, 8.5), 8.5, false, $ink);
                $pdf->text($cols['price'], $base, Rupiah::format($row['price']), 9, true, $ink, 'right');
                $dot = ['ok' => [22, 121, 76], 'low' => [217, 138, 0], 'out' => [179, 38, 30]][$row['state']];
                $pdf->rect($cols['stock'], $base - 6.5, 6, 6, $dot);
                $pdf->text($cols['stock'] + 10, $base, $row['stock'], 8.5, false, $ink);
                $y += $rowH;
            }
            $y += 6;
        }

        // Catatan di akhir dan footer di tiap halaman (nomor halaman baru diketahui setelah semua halaman jadi).
        if ($y + 40 > $bottom) {
            $y = $header(false);
        }
        $pdf->text($left, $y + 14, 'Harga dan stok dapat berubah sewaktu-waktu. Status stok mengikuti stok toko saat dokumen dibuat.', 8, false, $muted);
        $pdf->text($left, $y + 26, 'Pesan lewat '.config('store.name').' online atau hubungi kami untuk jumlah besar.', 8, false, $muted);

        $count = $pdf->pageCount();
        $contact = collect([config('store.address'), config('store.whatsapp') ? 'WA '.config('store.whatsapp') : null, config('store.email')])->filter()->implode('  |  ');
        for ($p = 0; $p < $count; $p++) {
            $pdf->setPage($p);
            $pdf->line($left, 812, $right, $line, 0.6);
            $pdf->text($left, 827, $pdf->fit($contact, 430, 7.5), 7.5, false, $muted);
            $pdf->text($right, 827, 'Halaman '.($p + 1).' dari '.$count, 7.5, false, $muted, 'right');
        }

        return $pdf->output('Pricelist '.config('store.name'));
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
