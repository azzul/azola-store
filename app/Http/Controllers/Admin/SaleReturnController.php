<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Services\SalesDocumentService;
use App\Support\Csv;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function __construct(private SalesDocumentService $docs) {}

    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));

        $query = SaleReturn::with('order')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->latest('date')->latest('id');

        if ($request->query('export') === 'csv') {
            return Csv::download('retur-penjualan-'.$from.'-'.$to.'.csv', ['Nomor', 'Tanggal', 'Faktur', 'Total', 'Potong piutang', 'Dikembalikan', 'Cara', 'Alasan'],
                (clone $query)->get()->map(fn ($r) => [$r->number, $r->date->format('Y-m-d'), $r->order?->number, $r->total, $r->offset_total, $r->paid_out, $r->refund_method, $r->reason]));
        }

        return view('admin.sale-returns.index', [
            'returns' => $query->paginate(30)->withQueryString(), 'from' => $from, 'to' => $to,
            'total' => (int) (clone $query)->reorder()->sum('total'),
        ]);
    }

    public function create(Request $request)
    {
        $order = null;
        $number = trim((string) $request->query('number'));
        if ($request->filled('order')) {
            $order = Order::find($request->query('order'));
        } elseif ($number !== '') {
            $order = Order::where('number', $number)->first();
            if (! $order) {
                return back()->withErrors(['number' => 'Nomor penjualan "'.$number.'" tidak ditemukan.']);
            }
        }

        if ($order?->isCancelled()) {
            return redirect()->route('admin.orders.show', $order)->withErrors(['order' => 'Pesanan yang dibatalkan tidak bisa diretur.']);
        }

        $returned = $order ? SaleReturnItem::whereIn('order_item_id', $order->items()->pluck('id'))->selectRaw('order_item_id, SUM(qty) q')->groupBy('order_item_id')->pluck('q', 'order_item_id') : collect();

        return view('admin.sale-returns.form', [
            'order' => $order?->load('items'), 'returned' => $returned, 'recent' => Order::where('status', '!=', 'cancelled')->latest('ordered_at')->limit(8)->get(['id', 'number', 'customer_name', 'grand_total']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'refund_method' => ['nullable', 'in:cash,transfer,deposit'],
            'reason' => ['nullable', 'string', 'max:200'],
            'items' => ['required', 'array'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.restock' => ['nullable', 'boolean'],
        ]);

        $rows = [];
        foreach ($data['items'] as $id => $row) {
            $rows[] = ['order_item_id' => $id, 'qty' => $row['qty'] ?? 0, 'restock' => $request->boolean("items.$id.restock")];
        }

        $return = $this->docs->createReturn(Order::findOrFail($data['order_id']), [
            'items' => $rows, 'date' => $data['date'], 'refund_method' => $data['refund_method'] ?? null, 'reason' => $data['reason'] ?? null,
        ], $request->user());

        return redirect()->route('admin.sale-returns.show', $return)->with('ok', 'Retur '.$return->number.' tercatat. Stok, piutang/kas, dan jurnal sudah disesuaikan.');
    }

    public function show(SaleReturn $saleReturn)
    {
        return view('admin.sale-returns.show', ['return' => $saleReturn->load('order', 'items.product', 'items.orderItem', 'journals.lines.account')]);
    }
}
