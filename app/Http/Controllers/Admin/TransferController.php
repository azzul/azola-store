<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockDocumentService;
use App\Support\LineEditor;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private StockDocumentService $docs) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'sent');

        return view('admin.transfers.index', [
            'transfers' => StockTransfer::with('from', 'to')->withCount('items')
                ->when($status !== 'all', fn ($w) => $w->where('status', $status))
                ->latest('date')->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => StockTransfer::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        $warehouses = Warehouse::where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get();

        return view('admin.transfers.form', [
            'warehouses' => $warehouses, 'initial' => LineEditor::hydrate(old('items', [])),
            'from' => old('from_warehouse_id', $request->query('from', Warehouse::mainId())),
            'to' => old('to_warehouse_id', $request->query('to', $warehouses->firstWhere('is_main', false)?->id)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'exists:warehouses,id'], 'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'date' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:200'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.unit' => ['nullable', 'string', 'max:20'], 'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ], ['to_warehouse_id.different' => 'Gudang tujuan harus berbeda dari gudang asal.']);

        $transfer = $this->docs->createTransfer($data, $request->user());

        return redirect()->route('admin.transfers.show', $transfer)->with('ok', 'Kiriman '.$transfer->number.' dibuat. Stok keluar dari gudang asal; catat penerimaannya saat barang tiba.');
    }

    public function show(StockTransfer $transfer)
    {
        return view('admin.transfers.show', ['transfer' => $transfer->load('items.product', 'from', 'to', 'journals.lines.account')]);
    }

    public function receive(Request $request, StockTransfer $transfer)
    {
        $data = $request->validate(['date' => ['required', 'date', 'before_or_equal:today'], 'received' => ['array'], 'received.*' => ['nullable', 'numeric', 'min:0']]);
        $this->docs->receiveTransfer($transfer, $data['received'] ?? [], $data['date'], $request->user());

        return redirect()->route('admin.transfers.show', $transfer)->with('ok', 'Penerimaan dicatat. Stok masuk ke gudang tujuan.');
    }

    public function cancel(Request $request, StockTransfer $transfer)
    {
        $this->docs->cancelTransfer($transfer, $request->user());

        return redirect()->route('admin.transfers.show', $transfer)->with('ok', 'Kiriman dibatalkan, stok kembali ke gudang asal.');
    }
}
