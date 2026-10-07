<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerDeposit;
use App\Models\Order;
use App\Services\SalesDocumentService;
use Illuminate\Http\Request;

/** Kembalian lebih transfer: pelanggan transfer melebihi tagihan; kelebihan dikembalikan atau disimpan sebagai DP. */
class OverpaymentController extends Controller
{
    public function __construct(private SalesDocumentService $docs) {}

    public function index(Request $request)
    {
        $order = null;
        if ($request->filled('number')) {
            $order = Order::where('number', trim((string) $request->query('number')))->first();
            if (! $order) {
                return redirect()->route('admin.overpayments.index')->withErrors(['number' => 'Nomor penjualan tidak ditemukan.']);
            }
        }

        return view('admin.overpayments.index', [
            'rows' => CustomerDeposit::with('order')->where('kind', 'overpay')->latest('date')->latest('id')->paginate(30),
            'order' => $order,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'], 'amount' => ['required', 'integer', 'min:1'], 'refund' => ['required', 'in:now,keep'],
        ]);

        $deposit = $this->docs->recordOverpayment(Order::findOrFail($data['order_id']), (int) $data['amount'], $data['refund'] === 'now', $request->user());

        return redirect()->route('admin.overpayments.index')->with('ok', 'Kelebihan transfer dicatat ('.$deposit->number.').'.($data['refund'] === 'now' ? ' Sudah dikembalikan ke pelanggan.' : ' Disimpan sebagai DP pelanggan.'));
    }
}
