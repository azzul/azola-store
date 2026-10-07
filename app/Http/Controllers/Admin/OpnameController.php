<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\StockOpname;
use App\Models\Warehouse;
use App\Services\StockDocumentService;
use Illuminate\Http\Request;

/** Stok opname: mulai sesi hitung, isi hasil (bisa dicicil), finalkan menjadi koreksi stok + jurnal. */
class OpnameController extends Controller
{
    public function __construct(private StockDocumentService $docs) {}

    /** Data stok opname: semua sesi, draft maupun final. */
    public function index(Request $request)
    {
        $opnames = StockOpname::with('warehouse')->withCount(['items', 'items as counted_count' => fn ($q) => $q->whereNotNull('counted_qty')])
            ->when($request->filled('status'), fn ($w) => $w->where('status', $request->query('status')))->latest('date')->latest('id')->paginate(30)->withQueryString();

        return view('admin.opnames.index', ['opnames' => $opnames]);
    }

    public function create()
    {
        return view('admin.opnames.form', [
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get(), 'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'], 'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'category_id' => ['nullable', 'exists:categories,id'], 'note' => ['nullable', 'string', 'max:200'],
        ]);
        $opname = $this->docs->startOpname($data, $request->user());

        return redirect()->route('admin.opnames.show', $opname)->with('ok', 'Sesi opname '.$opname->number.' dimulai. Isi hasil hitung fisik lalu simpan.');
    }

    public function show(Request $request, StockOpname $opname)
    {
        $opname->load('warehouse', 'journals');
        $items = $opname->items()->with('product')->get()->sortBy(fn ($i) => $i->product?->name)->values();
        $filter = $request->query('tampil');
        if ($filter === 'todo') {
            $items = $items->filter(fn ($i) => $i->counted_qty === null)->values();
        } elseif ($filter === 'diff') {
            $items = $items->filter(fn ($i) => $i->counted_qty !== null && (float) $i->counted_qty !== (float) $i->system_qty)->values();
        }

        return view('admin.opnames.show', ['opname' => $opname, 'items' => $items, 'filter' => $filter]);
    }

    public function save(Request $request, StockOpname $opname)
    {
        $data = $request->validate(['counts' => ['nullable', 'array'], 'counts.*' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'array'], 'notes.*' => ['nullable', 'string', 'max:200']]);
        $this->docs->saveCounts($opname->load('items.product'), $data['counts'] ?? [], $data['notes'] ?? []);

        if ($request->boolean('finalize')) {
            $this->docs->finalizeOpname($opname);

            return redirect()->route('admin.opnames.show', $opname)->with('ok', 'Opname difinalkan: selisih stok dan jurnal sudah dibukukan.');
        }

        return back()->with('ok', 'Hasil hitung disimpan.');
    }

    public function destroy(StockOpname $opname)
    {
        $this->docs->deleteOpname($opname);

        return redirect()->route('admin.opnames.index')->with('ok', 'Sesi opname dihapus.');
    }
}
