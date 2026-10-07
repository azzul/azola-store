<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Journal;
use App\Services\FinanceService;
use App\Services\JournalService;
use App\Support\Csv;
use Illuminate\Http\Request;

/** Biaya operasional dan kas masuk lain (tanpa lewat penjualan). Pembatalan = jurnal pembalik. */
class ExpenseController extends Controller
{
    public function __construct(private FinanceService $finance, private JournalService $journals) {}

    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));

        $query = Journal::with('lines.account')->whereIn('type', ['expense', 'income'])
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->filled('kind'), fn ($w) => $w->where('type', $request->query('kind')))
            ->latest('date')->latest('id');

        $rows = (clone $query)->get();
        $active = $rows->filter(fn ($j) => ! $j->isReversed());

        if ($request->query('export') === 'csv') {
            return Csv::download('biaya-kas-'.$from.'-'.$to.'.csv', ['Nomor', 'Tanggal', 'Jenis', 'Keterangan', 'Jumlah', 'Status'],
                $rows->map(fn ($j) => [$j->number, $j->date->format('Y-m-d'), $j->type, $j->description, $j->total, $j->isReversed() ? 'dibatalkan' : 'sah']));
        }

        return view('admin.expenses.index', [
            'journals' => $rows, 'from' => $from, 'to' => $to,
            'expense' => (int) $active->where('type', 'expense')->sum('total'), 'income' => (int) $active->where('type', 'income')->sum('total'),
            'expenseAccounts' => Account::where('is_active', true)->where('type', 'expense')->orderBy('code')->get(),
            'incomeAccounts' => Account::where('is_active', true)->whereIn('type', ['revenue', 'equity', 'liability'])->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:expense,income'], 'account_id' => ['required', 'exists:accounts,id'], 'via' => ['required', 'in:cash,bank'],
            'amount' => ['required', 'integer', 'min:1'], 'date' => ['required', 'date', 'before_or_equal:today'], 'description' => ['nullable', 'string', 'max:200'],
        ]);
        $journal = $this->finance->cashEntry($data, $request->user());

        return back()->with('ok', ($data['kind'] === 'expense' ? 'Biaya' : 'Kas masuk').' dicatat di jurnal '.$journal->number.'.');
    }

    public function cancel(Request $request, Journal $journal)
    {
        abort_unless(in_array($journal->type, ['expense', 'income'], true), 404);
        $this->journals->reverse($journal, 'dibatalkan', $request->user()->id, true);

        return back()->with('ok', 'Dibatalkan: jurnal pembalik dibuat pada tanggal yang sama.');
    }
}
