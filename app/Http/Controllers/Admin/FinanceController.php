<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Journal;
use App\Services\ReconciliationService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function journals(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $journals = Journal::query()
            ->when($request->filled('type'), fn ($w) => $w->where('type', $request->query('type')))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")))
            ->latest('date')->latest('id')->paginate(30)->withQueryString();

        return view('admin.finance.journals', ['journals' => $journals, 'q' => $q]);
    }

    public function journal(Journal $journal)
    {
        return view('admin.finance.journal', ['journal' => $journal->load('lines.account', 'user', 'source')]);
    }

    /** Neraca saldo + laba rugi sederhana untuk rentang tanggal. */
    public function report(Request $request)
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $data['from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to = $data['to'] ?? now()->format('Y-m-d');
        $toEnd = $to.' 23:59:59'; // kolom date bisa tersimpan dengan jam 00:00:00

        $rows = Account::orderBy('code')->get()->map(function (Account $a) use ($from, $toEnd) {
            $net = $a->netDebit($from, $toEnd);

            return ['account' => $a, 'debit' => max($net, 0), 'credit' => max(-$net, 0), 'net' => $net];
        });

        $sum = fn (array $types) => $rows->filter(fn ($r) => in_array($r['account']->type, $types, true))->sum(fn ($r) => -$r['net']);

        $revenue = (int) $sum(['revenue']);
        $cogs = -(int) $sum(['cogs']);
        $expense = -(int) $sum(['expense']);

        return view('admin.finance.report', [
            'from' => $from, 'to' => $to, 'rows' => $rows,
            'revenue' => $revenue, 'cogs' => $cogs, 'expense' => $expense,
            'gross' => $revenue - $cogs, 'profit' => $revenue - $cogs - $expense,
            'debitTotal' => (int) $rows->sum('debit'), 'creditTotal' => (int) $rows->sum('credit'),
        ]);
    }

    public function reconcile(ReconciliationService $reconciliation)
    {
        return view('admin.finance.reconcile', ['result' => $reconciliation->run()]);
    }
}
