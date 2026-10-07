<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\PurchaseService;
use App\Services\ReportService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Hutang (daftar + umur), pembayaran hutang, piutang supplier, dan kartu hutang. */
class PayableController extends Controller
{
    public function __construct(private PurchaseService $purchases, private ReportService $reports) {}

    public function index(Request $request)
    {
        $open = Purchase::with('supplier')->where('status', 'posted')->whereColumn('paid_total', '<', 'grand_total')
            ->when($request->filled('supplier'), fn ($w) => $w->where('supplier_id', $request->query('supplier')))
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('date')->get();

        $buckets = ['current' => 0, 'd30' => 0, 'd60' => 0, 'over' => 0];
        foreach ($open as $p) {
            $late = $p->due_date ? $p->due_date->diffInDays(today(), false) : 0;
            $key = $late <= 0 ? 'current' : ($late <= 30 ? 'd30' : ($late <= 60 ? 'd60' : 'over'));
            $buckets[$key] += $p->outstanding();
        }

        if ($request->query('export') === 'csv') {
            return Csv::download('hutang-'.today()->format('Y-m-d').'.csv', ['Faktur', 'Supplier', 'Tanggal', 'Jatuh tempo', 'Total', 'Dibayar', 'Sisa'],
                $open->map(fn ($p) => [$p->number, $p->supplier?->name, $p->date->format('Y-m-d'), $p->due_date?->format('Y-m-d'), $p->grand_total, $p->paid_total, $p->outstanding()]));
        }

        $bySupplier = $open->groupBy('supplier_id')->map(fn ($rows) => [
            'supplier' => $rows->first()->supplier, 'count' => $rows->count(),
            'total' => $rows->sum(fn ($p) => $p->outstanding()),
            'overdue' => $rows->filter(fn ($p) => $p->isOverdue())->sum(fn ($p) => $p->outstanding()),
        ])->sortByDesc('total');

        return view('admin.payables.index', [
            'open' => $open, 'buckets' => $buckets, 'bySupplier' => $bySupplier, 'total' => $open->sum(fn ($p) => $p->outstanding()),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ---- Pembayaran hutang

    public function payments(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));

        $payments = SupplierPayment::with('supplier')->where('direction', 'pay')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->filled('supplier'), fn ($w) => $w->where('supplier_id', $request->query('supplier')))
            ->latest('date')->latest('id')->paginate(30)->withQueryString();

        return view('admin.payables.payments', [
            'payments' => $payments, 'from' => $from, 'to' => $to,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'total' => SupplierPayment::where('direction', 'pay')->where('status', 'posted')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->sum('amount'),
        ]);
    }

    public function createPayment(Request $request)
    {
        $supplier = $request->filled('supplier') ? Supplier::find($request->query('supplier')) : null;

        return view('admin.payables.pay', [
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']), 'supplier' => $supplier,
            'open' => $supplier ? Purchase::where('supplier_id', $supplier->id)->where('status', 'posted')->whereColumn('paid_total', '<', 'grand_total')->orderBy('due_date')->orderBy('date')->get() : collect(),
            'purchaseId' => (int) $request->query('purchase'),
        ]);
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'], 'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['cash', 'bank'])], 'date' => ['required', 'date', 'before_or_equal:today'],
            'purchases' => ['nullable', 'array'], 'purchases.*' => ['integer'], 'note' => ['nullable', 'string', 'max:200'],
        ]);

        $payment = $this->purchases->pay(Supplier::findOrFail($data['supplier_id']), (int) $data['amount'], $data['method'], $data['date'], array_values($data['purchases'] ?? []) ?: null, $data['note'] ?? null, $request->user());

        return redirect()->route('admin.supplier-payments.index')->with('ok', 'Pembayaran '.$payment->number.' dicatat. Hutang berkurang dan jurnal terbentuk.');
    }

    public function cancelPayment(Request $request, SupplierPayment $payment)
    {
        $this->purchases->cancelPayment($payment, $request->user());

        return back()->with('ok', 'Pembayaran dibatalkan dan jurnalnya dibalik.');
    }

    // ---- Piutang supplier

    public function receivables(Request $request)
    {
        $suppliers = Supplier::orderBy('name')->get()->map(fn ($s) => ['supplier' => $s, 'balance' => $s->receivable()])->filter(fn ($r) => $r['balance'] > 0)->values();
        $supplier = $request->filled('supplier') ? Supplier::find($request->query('supplier')) : null;
        $from = $request->query('from', now()->subMonths(3)->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));

        return view('admin.payables.receivables', [
            'rows' => $suppliers, 'total' => $suppliers->sum('balance'), 'supplier' => $supplier, 'from' => $from, 'to' => $to,
            'card' => $supplier ? $this->reports->supplierReceivableCard($supplier, $from, $to) : null,
            'receipts' => SupplierPayment::with('supplier')->where('direction', 'receive')->latest('date')->latest('id')->limit(20)->get(),
            'allSuppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function receive(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'], 'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['cash', 'bank'])], 'date' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:200'],
        ]);

        $payment = $this->purchases->receive(Supplier::findOrFail($data['supplier_id']), (int) $data['amount'], $data['method'], $data['date'], $data['note'] ?? null, $request->user());

        return redirect()->route('admin.supplier-receivables.index', ['supplier' => $data['supplier_id']])->with('ok', 'Penerimaan '.$payment->number.' dicatat.');
    }

    // ---- Kartu hutang

    public function card(Request $request)
    {
        $supplier = $request->filled('supplier') ? Supplier::find($request->query('supplier')) : null;
        $from = $request->query('from', now()->subMonths(3)->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $card = $supplier ? $this->reports->supplierPayableCard($supplier, $from, $to) : null;

        if ($card && $request->query('export') === 'csv') {
            return Csv::download('kartu-hutang-'.$supplier->id.'.csv', ['Tanggal', 'Nomor', 'Keterangan', 'Bertambah', 'Berkurang', 'Saldo'],
                collect($card['rows'])->map(fn ($r) => [\Carbon\Carbon::parse($r['line']->date)->format('Y-m-d'), $r['line']->number, $r['line']->description, $r['line']->credit, $r['line']->debit, $r['balance']]));
        }

        return view('admin.payables.card', [
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']), 'supplier' => $supplier, 'from' => $from, 'to' => $to, 'card' => $card,
        ]);
    }
}
