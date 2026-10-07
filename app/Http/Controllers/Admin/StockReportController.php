<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ReportService;
use App\Support\Csv;
use App\Support\Qty;
use Illuminate\Http\Request;

/** Mutasi stok (semua barang) dan kartu stok (satu barang), per gudang. */
class StockReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function mutation(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $warehouse = (int) $request->query('warehouse') ?: null;
        $q = trim((string) $request->query('q')) ?: null;
        $rows = $this->reports->stockMutation($from, $to, $warehouse, $q);

        if ($request->query('export') === 'csv') {
            return Csv::download('mutasi-stok-'.$from.'-'.$to.'.csv', ['SKU', 'Barang', 'Satuan', 'Awal', 'Masuk', 'Keluar', 'Akhir', 'Nilai akhir'],
                $rows->map(fn ($r) => [$r['product']->sku, $r['product']->name, $r['product']->unit, $r['opening'] / 1000, $r['in'] / 1000, $r['out'] / 1000, $r['closing'] / 1000, $r['value']]));
        }

        return view('admin.stock-reports.mutation', [
            'rows' => $rows, 'from' => $from, 'to' => $to, 'q' => $q, 'warehouse' => $warehouse,
            'warehouses' => Warehouse::orderByDesc('is_main')->orderBy('name')->get(),
        ]);
    }

    public function card(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $warehouse = (int) $request->query('warehouse') ?: null;
        $product = $request->filled('product') ? Product::find($request->query('product')) : null;
        $card = $product ? $this->reports->stockCard($product, $from, $to, $warehouse) : null;

        if ($card && $request->query('export') === 'csv') {
            return Csv::download('kartu-stok-'.$product->sku.'.csv', ['Waktu', 'Jenis', 'Keterangan', 'Gudang', 'Masuk', 'Keluar', 'Saldo'],
                collect($card['rows'])->map(fn ($r) => [$r['m']->created_at, $r['m']->type, $r['m']->note, $r['m']->warehouse, $r['in'] / 1000, $r['out'] / 1000, $r['balance'] / 1000]));
        }

        return view('admin.stock-reports.card', [
            'product' => $product, 'card' => $card, 'from' => $from, 'to' => $to, 'warehouse' => $warehouse,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'variant_name']),
            'warehouses' => Warehouse::orderByDesc('is_main')->orderBy('name')->get(),
        ]);
    }
}
