<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Journal;
use App\Services\ReconciliationService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    /** Jurnal umum: semua jurnal, dengan filter periode, jenis, dan pencarian. */
    public function journals(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));

        $query = Journal::query()->with('lines.account')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->filled('type'), fn ($w) => $w->where('type', $request->query('type')))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")))
            ->latest('date')->latest('id');

        if ($request->query('export') === 'csv') {
            $rows = [];
            foreach ((clone $query)->get() as $j) {
                foreach ($j->lines as $l) {
                    $rows[] = [$j->number, $j->date->format('Y-m-d'), $j->type, $j->description, $l->account->code, $l->account->name, $l->debit, $l->credit];
                }
            }

            return \App\Support\Csv::download('jurnal-umum-'.$from.'-'.$to.'.csv', ['Nomor', 'Tanggal', 'Jenis', 'Keterangan', 'Kode akun', 'Akun', 'Debit', 'Kredit'], $rows);
        }

        return view('admin.finance.journals', [
            'journals' => $query->paginate(25)->withQueryString(), 'q' => $q, 'from' => $from, 'to' => $to,
            'types' => Journal::query()->distinct()->orderBy('type')->pluck('type'),
        ]);
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
