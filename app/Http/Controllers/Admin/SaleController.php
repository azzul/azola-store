<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceLevel;
use App\Services\LineResolver;
use App\Services\OrderService;
use App\Support\Csv;
use App\Support\LineEditor;
use App\Support\Qty;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Laporan penjualan (kasir, semua kanal, batal, per item) dan input penjualan dari admin. */
class SaleController extends Controller
{
    public function __construct(private OrderService $orders, private LineResolver $resolver) {}

    /** Penjualan kasir: transaksi dari aplikasi POS (desktop/android). */
    public function pos(Request $request)
    {
        return $this->listing($request, 'admin.sales.pos', 'Penjualan kasir', fn ($q) => $q->whereIn('channel', ['pos_desktop', 'pos_android'])->where('status', '!=', 'cancelled'), true);
    }

    public function index(Request $request)
    {
        return $this->listing($request, 'admin.sales.index', 'Daftar penjualan', fn ($q) => $q->where('status', '!=', 'cancelled'), true);
    }

    public function cancelled(Request $request)
    {
        return $this->listing($request, 'admin.sales.cancelled', 'Penjualan batal', fn ($q) => $q->where('status', 'cancelled'), false);
    }

    /** Penjualan detail: satu baris per barang terjual, lengkap dengan HPP dan laba kotor. */
    public function items(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $q = trim((string) $request->query('q'));

        $query = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->whereDate('orders.ordered_at', '>=', $from)->whereDate('orders.ordered_at', '<=', $to)
            ->when($request->filled('channel'), fn ($w) => $w->where('orders.channel', $request->query('channel')))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('order_items.name', 'like', "%{$q}%")->orWhere('order_items.sku', 'like', "%{$q}%")->orWhere('orders.number', 'like', "%{$q}%")))
            ->select('order_items.*', 'orders.number as order_number', 'orders.ordered_at', 'orders.channel', 'orders.customer_name', 'orders.id as oid')
            ->orderByDesc('orders.ordered_at')->orderByDesc('order_items.id');

        if ($request->query('export') === 'csv') {
            return Csv::download('penjualan-item-'.$from.'-'.$to.'.csv', ['Nomor', 'Tanggal', 'Kanal', 'Pelanggan', 'SKU', 'Barang', 'Jumlah', 'Harga', 'Diskon', 'Subtotal', 'HPP', 'Laba kotor'],
                (clone $query)->get()->map(fn ($i) => [$i->order_number, $i->ordered_at, $i->channel, $i->customer_name, $i->sku, $i->name, $i->qty + 0, $i->price, $i->discount, $i->line_total, $this->cost($i), $i->line_total - $this->cost($i)]));
        }

        $all = (clone $query)->get();
        $sum = ['qty' => 0, 'revenue' => 0, 'cost' => 0];
        foreach ($all as $i) {
            $sum['qty'] += Qty::toMilli($i->qty);
            $sum['revenue'] += $i->line_total;
            $sum['cost'] += $this->cost($i);
        }

        return view('admin.sales.items', ['items' => $query->paginate(40)->withQueryString(), 'sum' => $sum, 'from' => $from, 'to' => $to, 'q' => $q]);
    }

    public function create()
    {
        return view('admin.sales.form', $this->formData(old('items', [])));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'buyer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'price_level_id' => ['nullable', 'exists:price_levels,id'],
            'payment_method' => ['required', Rule::in(OrderService::METHODS)],
            'paid_total' => ['nullable', 'integer', 'min:0'],
            'order_discount' => ['nullable', 'integer', 'min:0'],
            'shipping_fee' => ['nullable', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'integer', 'min:0'],
        ], ['items.required' => 'Tambahkan minimal satu barang.']);

        $buyer = ! empty($data['buyer_id']) ? Customer::find($data['buyer_id']) : null;
        $levelId = (int) ($data['price_level_id'] ?? 0) ?: $buyer?->price_level_id;

        // Harga & satuan dari form diubah ke satuan dasar; selisih dari harga daftar menjadi diskon baris.
        $items = [];
        foreach ($this->resolver->resolve($data['items']) as $line) {
            $list = \App\Support\Qty::value($line['base_milli'], $line['product']->priceFor($levelId));
            if ($line['line_total'] > $list) {
                throw ValidationException::withMessages(['items' => $line['product']->name.': harga jual tidak boleh di atas harga daftar. Ubah harga di Master > Harga.']);
            }
            $items[] = ['product_id' => $line['product']->id, 'qty' => Qty::fromMilli($line['base_milli']), 'discount' => $list - $line['line_total']];
        }

        [$order] = $this->orders->create([
            'items' => $items, 'buyer_id' => $buyer?->id, 'price_level_id' => $levelId,
            'customer_name' => $buyer?->name ?? ($data['customer_name'] ?? null), 'customer_phone' => $buyer?->phone ?? ($data['customer_phone'] ?? null),
            'payment_method' => $data['payment_method'], 'paid_total' => $data['paid_total'] ?? null,
            'order_discount' => $data['order_discount'] ?? 0, 'shipping_fee' => $data['shipping_fee'] ?? 0,
            'due_date' => $data['due_date'] ?? null, 'notes' => $data['notes'] ?? null,
        ], 'admin', $request->user());

        return redirect()->route('admin.orders.show', $order)->with('ok', 'Penjualan '.$order->number.' tersimpan. Stok berkurang dan jurnal terbentuk.');
    }

    private function cost(object $i): int
    {
        return Qty::value(Qty::toMilli($i->qty), (int) $i->unit_cost);
    }

    private function formData(array $rows): array
    {
        return [
            'initial' => LineEditor::hydrate($rows),
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone', 'price_level_id']),
            'levels' => PriceLevel::orderBy('id')->get(),
            'methods' => OrderService::METHODS,
        ];
    }

    private function listing(Request $request, string $route, string $title, \Closure $scope, bool $csvPay)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $q = trim((string) $request->query('q'));

        $query = $scope(Order::query())
            ->whereDate('ordered_at', '>=', $from)->whereDate('ordered_at', '<=', $to)
            ->when($request->filled('channel'), fn ($w) => $w->where('channel', $request->query('channel')))
            ->when($request->filled('method'), fn ($w) => $w->where('payment_method', $request->query('method')))
            ->when($request->query('bayar') === 'unpaid', fn ($w) => $w->whereColumn('paid_total', '<', 'grand_total'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('customer_name', 'like', "%{$q}%")->orWhere('customer_phone', 'like', "%{$q}%")))
            ->latest('ordered_at')->latest('id');

        if ($request->query('export') === 'csv') {
            return Csv::download(str($title)->slug().'-'.$from.'-'.$to.'.csv', ['Nomor', 'Waktu', 'Kanal', 'Pelanggan', 'Metode', 'Total', 'Dibayar', 'Retur', 'Sisa', 'HPP', 'Status'],
                (clone $query)->get()->map(fn (Order $o) => [$o->number, $o->ordered_at, $o->channel, $o->customer_name, $o->payment_method, $o->grand_total, $o->paid_total, $o->returned_total, $o->outstanding(), $o->cogs_total, $o->status]));
        }

        $sum = (clone $query)->reorder()->selectRaw('COUNT(*) n, COALESCE(SUM(grand_total),0) total, COALESCE(SUM(paid_total),0) paid, COALESCE(SUM(cogs_total),0) cogs, COALESCE(SUM(returned_total),0) ret')->first();

        return view('admin.sales.index', [
            'orders' => $query->paginate(30)->withQueryString(), 'sum' => $sum, 'from' => $from, 'to' => $to, 'q' => $q,
            'title' => $title, 'route' => $route, 'cancelled' => $route === 'admin.sales.cancelled',
        ]);
    }
}
