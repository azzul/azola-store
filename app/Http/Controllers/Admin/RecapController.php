<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Support\Csv;
use Illuminate\Http\Request;

/** Rekap pendapatan: uang masuk per metode (tunai, debit, QRIS, transfer, DP), per kanal, dan per hari. */
class RecapController extends Controller
{
    public const METHODS = ['cash' => 'Tunai', 'debit' => 'Debit', 'qris' => 'QRIS', 'transfer' => 'Transfer', 'cod' => 'COD', 'deposit' => 'Pakai DP'];

    public function index(Request $request, ReportService $reports)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));
        $recap = $reports->paymentRecap($from, $to);

        if ($request->query('export') === 'csv') {
            return Csv::download('rekap-pendapatan-'.$from.'-'.$to.'.csv', array_merge(['Tanggal'], array_values(self::METHODS), ['Total']),
                collect($recap['by_day'])->map(fn ($row, $day) => array_merge([$day], array_map(fn ($m) => $row[$m] ?? 0, array_keys(self::METHODS)), [$row['_total']]))->values());
        }

        return view('admin.recap.index', ['recap' => $recap, 'from' => $from, 'to' => $to, 'methods' => self::METHODS]);
    }
}
