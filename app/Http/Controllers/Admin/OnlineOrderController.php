<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Order online per tahap: baru → perlu proses → sedang dikirim → selesai (atau batal). */
class OnlineOrderController extends Controller
{
    public const STAGES = [
        'new' => ['Order baru', 'Menunggu pembayaran atau konfirmasi.'],
        'process' => ['Perlu proses', 'Sudah lunas/dikonfirmasi: kemas lalu kirim atau siapkan untuk diambil.'],
        'shipped' => ['Sedang dikirim', 'Sudah diserahkan ke kurir. Tandai selesai saat diterima pelanggan.'],
        'done' => ['Selesai', 'Pesanan sudah diterima pelanggan.'],
        'cancelled' => ['Batal', 'Pesanan dibatalkan; stok sudah kembali.'],
    ];

    public function __construct(private OrderService $orders) {}

    public function index(Request $request, string $stage)
    {
        abort_unless(isset(self::STAGES[$stage]), 404);
        $q = trim((string) $request->query('q'));

        $list = Order::with('items')->where('channel', 'web')->where('fulfillment', $stage)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('customer_name', 'like', "%{$q}%")->orWhere('customer_phone', 'like', "%{$q}%")->orWhere('tracking_no', 'like', "%{$q}%")))
            ->latest('ordered_at')->paginate(20)->withQueryString();

        return view('admin.online.index', [
            'stage' => $stage, 'meta' => self::STAGES, 'orders' => $list, 'q' => $q,
            'counts' => Order::where('channel', 'web')->selectRaw('fulfillment, COUNT(*) n')->groupBy('fulfillment')->pluck('n', 'fulfillment'),
            'methods' => OrderService::METHODS,
        ]);
    }

    public function confirm(Order $order)
    {
        $this->web($order);
        $this->orders->confirm($order);

        return back()->with('ok', $order->number.' dikonfirmasi: masuk tahap Perlu proses.');
    }

    public function ship(Request $request, Order $order)
    {
        $this->web($order);
        $data = $request->validate(['courier' => ['required', 'string', 'max:60'], 'tracking_no' => ['nullable', 'string', 'max:80']]);
        $this->orders->ship($order, $data['courier'], $data['tracking_no'] ?? null);

        return back()->with('ok', $order->number.' ditandai dikirim lewat '.$data['courier'].'.');
    }

    public function complete(Order $order)
    {
        $this->web($order);
        if ($order->payment_status !== 'paid' && $order->payment_method !== 'cod') {
            throw ValidationException::withMessages(['order' => 'Pesanan belum lunas. Catat pembayaran dulu.']);
        }
        if ($order->payment_method === 'cod' && $order->outstanding() > 0) {
            // COD: uang diterima kurir saat barang tiba, dicatat lunas sebagai tunai.
            $this->orders->recordPayment($order, $order->outstanding(), 'cash', request()->user());
            $order->refresh();
        }
        $this->orders->complete($order);

        return back()->with('ok', $order->number.' selesai.');
    }

    private function web(Order $order): void
    {
        abort_unless($order->channel === 'web', 404);
    }
}
