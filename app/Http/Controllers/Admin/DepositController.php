<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\Order;
use App\Services\SalesDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** DP customer (uang muka dan kelebihan bayar): terima, pakai untuk pesanan, atau kembalikan. */
class DepositController extends Controller
{
    public function __construct(private SalesDocumentService $docs) {}

    public function index(Request $request)
    {
        $q = CustomerDeposit::with('customer', 'order')
            ->when($request->query('open'), fn ($w) => $w->whereColumn('amount', '>', DB::raw('used_total + refunded_total')))
            ->when($request->filled('customer'), fn ($w) => $w->where('customer_id', $request->query('customer')))
            ->latest('date')->latest('id');

        return view('admin.deposits.index', [
            'deposits' => $q->paginate(30)->withQueryString(),
            'balance' => (int) CustomerDeposit::selectRaw('COALESCE(SUM(amount - used_total - refunded_total),0) v')->value('v'),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.deposits.form', ['customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']), 'customerId' => $request->query('customer')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'], 'customer_name' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'integer', 'min:1'], 'method' => ['required', 'in:cash,transfer,qris,debit'],
            'date' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:200'],
        ]);

        $deposit = $this->docs->createDeposit($data + ['kind' => 'dp'], $request->user());

        return redirect()->route('admin.deposits.show', $deposit)->with('ok', 'DP '.$deposit->number.' diterima dan dijurnal sebagai kewajiban.');
    }

    public function show(CustomerDeposit $deposit)
    {
        $orders = $deposit->customer_id
            ? Order::where('buyer_id', $deposit->customer_id)->where('status', '!=', 'cancelled')->get()->filter(fn ($o) => $o->outstanding() > 0)
            : collect();

        return view('admin.deposits.show', ['deposit' => $deposit->load('customer', 'order', 'journals.lines.account'), 'orders' => $orders]);
    }

    public function apply(Request $request, CustomerDeposit $deposit)
    {
        $data = $request->validate(['order_id' => ['required', 'exists:orders,id'], 'amount' => ['required', 'integer', 'min:1']]);
        $this->docs->applyDeposit($deposit, Order::findOrFail($data['order_id']), (int) $data['amount'], $request->user());

        return back()->with('ok', 'DP dipakai untuk melunasi pesanan.');
    }

    public function refund(Request $request, CustomerDeposit $deposit)
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1'], 'method' => ['required', 'in:cash,transfer']]);
        $this->docs->refundDeposit($deposit, (int) $data['amount'], $data['method'], $request->user());

        return back()->with('ok', 'DP dikembalikan ke customer.');
    }
}
