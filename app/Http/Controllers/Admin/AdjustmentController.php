<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\StockDocumentService;
use App\Support\Csv;
use App\Support\LineEditor;
use Illuminate\Http\Request;

/** Penyesuaian stok: rusak, hilang, kedaluwarsa, penyusutan, koreksi (selisih + atau -). */
class AdjustmentController extends Controller
{
    public function __construct(private StockDocumentService $docs) {}

    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));

        $query = StockAdjustment::with('warehouse')->withCount('items')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->filled('reason'), fn ($w) => $w->where('reason', $request->query('reason')))->latest('date')->latest('id');

        if ($request->query('export') === 'csv') {
            return Csv::download('penyesuaian-stok-'.$from.'-'.$to.'.csv', ['Nomor', 'Tanggal', 'Alasan', 'Gudang', 'Nilai bersih', 'Catatan'],
                (clone $query)->get()->map(fn ($a) => [$a->number, $a->date->format('Y-m-d'), StockAdjustment::REASONS[$a->reason] ?? $a->reason, $a->warehouse?->name, $a->value_total, $a->note]));
        }

        return view('admin.adjustments.index', [
            'adjustments' => $query->paginate(30)->withQueryString(), 'from' => $from, 'to' => $to,
            'total' => (int) (clone $query)->reorder()->sum('value_total'), 'reasons' => StockAdjustment::REASONS,
        ]);
    }

    public function create()
    {
        return view('admin.adjustments.form', [
            'initial' => LineEditor::hydrate(old('items', [])), 'reasons' => StockAdjustment::REASONS,
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'], 'reason' => ['required', 'in:'.implode(',', array_keys(StockAdjustment::REASONS))],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'], 'note' => ['nullable', 'string', 'max:200'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'exists:products,id'], 'items.*.qty_change' => ['required', 'numeric'],
        ], ['items.required' => 'Tambahkan minimal satu barang.']);

        $adjustment = $this->docs->createAdjustment($data, $request->user());

        return redirect()->route('admin.adjustments.show', $adjustment)->with('ok', 'Penyesuaian '.$adjustment->number.' tersimpan. Stok dan jurnal sudah diperbarui.');
    }

    public function show(StockAdjustment $adjustment)
    {
        return view('admin.adjustments.show', ['adjustment' => $adjustment->load('items.product', 'warehouse', 'journals.lines.account'), 'reasons' => StockAdjustment::REASONS]);
    }
}
