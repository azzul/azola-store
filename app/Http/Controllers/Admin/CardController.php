<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\ReportService;
use App\Support\Csv;
use Illuminate\Http\Request;

/** Kartu piutang customer (dan DP-nya), diturunkan dari jurnal sehingga cocok dengan buku besar. */
class CardController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function receivable(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $customer = $request->filled('customer') ? Customer::find($request->query('customer')) : null;

        $card = $customer ? $this->reports->customerReceivableCard($customer, $from, $to) : null;
        $deposit = $customer ? $this->reports->customerDepositCard($customer, $from, $to) : null;

        if ($customer && $request->query('export') === 'csv') {
            return Csv::download('kartu-piutang-'.str($customer->name)->slug().'.csv', ['Tanggal', 'Nomor', 'Keterangan', 'Debit', 'Kredit', 'Saldo'],
                collect($card['rows'])->map(fn ($r) => [substr((string) $r['line']->date, 0, 10), $r['line']->number, $r['line']->description, $r['line']->debit, $r['line']->credit, $r['balance']]));
        }

        return view('admin.cards.receivable', [
            'customers' => Customer::orderBy('name')->get(['id', 'name']), 'customer' => $customer, 'card' => $card, 'deposit' => $deposit,
            'from' => $from, 'to' => $to,
            'open' => $customer ? $customer->orders()->where('status', '!=', 'cancelled')->get()->filter(fn ($o) => $o->outstanding() > 0) : collect(),
        ]);
    }
}
