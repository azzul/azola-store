<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Support\Csv;
use Illuminate\Http\Request;

/** Mutasi saldo: saldo awal, mutasi debit/kredit, dan saldo akhir tiap akun pada periode. */
class BalanceController extends Controller
{
    public const TYPES = ['asset' => 'Aset (kas, bank, piutang, persediaan)', 'liability' => 'Kewajiban (hutang, DP)', 'equity' => 'Modal', 'revenue' => 'Pendapatan', 'cogs' => 'HPP', 'expense' => 'Beban'];

    public function index(Request $request, ReportService $reports)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $type = array_key_exists($request->query('type', ''), self::TYPES) ? $request->query('type') : null;
        $trial = $reports->trial($from, $to, $type ? [$type] : ['asset', 'liability', 'equity']);

        if ($request->query('export') === 'csv') {
            return Csv::download('mutasi-saldo-'.$from.'-'.$to.'.csv', ['Kode', 'Akun', 'Saldo awal', 'Debit', 'Kredit', 'Saldo akhir'],
                collect($trial['rows'])->map(fn ($r) => [$r['account']->code, $r['account']->name, $r['opening'], $r['debit'], $r['credit'], $r['closing']]));
        }

        return view('admin.balances.index', ['trial' => $trial, 'from' => $from, 'to' => $to, 'type' => $type, 'types' => self::TYPES]);
    }
}
