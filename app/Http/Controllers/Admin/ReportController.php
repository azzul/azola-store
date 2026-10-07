<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\ReportService;
use App\Support\Csv;
use Illuminate\Http\Request;

/** Buku besar, laba rugi, neraca, dan neraca saldo. Semua dari jurnal, jadi saling cocok. */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function ledger(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $account = $request->filled('account') ? Account::find($request->query('account')) : null;
        $ledger = $account ? $this->reports->ledger($account, $from, $to) : null;

        if ($ledger && $request->query('export') === 'csv') {
            return Csv::download('buku-besar-'.$account->code.'-'.$from.'-'.$to.'.csv', ['Tanggal', 'Jurnal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'],
                collect($ledger['rows'])->map(fn ($r) => [substr((string) $r['line']->date, 0, 10), $r['line']->number, $r['line']->description, $r['line']->debit, $r['line']->credit, $r['balance']]));
        }

        return view('admin.reports.ledger', ['accounts' => Account::orderBy('code')->get(), 'account' => $account, 'ledger' => $ledger, 'from' => $from, 'to' => $to]);
    }

    /** Jurnal laba rugi. */
    public function income(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $statement = $this->reports->incomeStatement($from, $to);

        if ($request->query('export') === 'csv') {
            $rows = [];
            foreach (['revenue' => 'Pendapatan', 'cogs' => 'HPP', 'expense' => 'Beban'] as $k => $label) {
                foreach ($statement[$k] as $r) {
                    $rows[] = [$label, $r['account']->code, $r['account']->name, $r['amount']];
                }
            }
            $rows[] = ['Laba kotor', '', '', $statement['totals']['gross']];
            $rows[] = ['Laba bersih', '', '', $statement['totals']['net']];

            return Csv::download('laba-rugi-'.$from.'-'.$to.'.csv', ['Kelompok', 'Kode', 'Akun', 'Jumlah'], $rows);
        }

        return view('admin.reports.income', ['s' => $statement, 'from' => $from, 'to' => $to]);
    }

    /** Jurnal neraca per tanggal. */
    public function balance(Request $request)
    {
        $asOf = $request->query('to', today()->format('Y-m-d'));
        $sheet = $this->reports->balanceSheet($asOf);

        if ($request->query('export') === 'csv') {
            $rows = [];
            foreach (['asset' => 'Aset', 'liability' => 'Kewajiban', 'equity' => 'Modal'] as $k => $label) {
                foreach ($sheet[$k] as $r) {
                    $rows[] = [$label, $r['account']->code, $r['account']->name, $r['amount']];
                }
            }
            $rows[] = ['Modal', '', 'Laba berjalan', $sheet['earnings']];

            return Csv::download('neraca-'.$asOf.'.csv', ['Kelompok', 'Kode', 'Akun', 'Jumlah'], $rows);
        }

        return view('admin.reports.balance', ['sheet' => $sheet, 'asOf' => $asOf]);
    }

    public function trial(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $trial = $this->reports->trial($from, $to);

        if ($request->query('export') === 'csv') {
            return Csv::download('neraca-saldo-'.$from.'-'.$to.'.csv', ['Kode', 'Akun', 'Saldo awal', 'Debit', 'Kredit', 'Saldo akhir'],
                collect($trial['rows'])->map(fn ($r) => [$r['account']->code, $r['account']->name, $r['opening'], $r['debit'], $r['credit'], $r['closing']]));
        }

        return view('admin.reports.trial', ['trial' => $trial, 'from' => $from, 'to' => $to]);
    }
}
