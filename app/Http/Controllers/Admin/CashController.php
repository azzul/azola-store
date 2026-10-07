<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashClosing;
use App\Services\FinanceService;
use Illuminate\Http\Request;

/** Rekap kas harian: hitung uang di laci per pecahan, bandingkan dengan kas menurut sistem. */
class CashController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    public function index(Request $request)
    {
        $date = $request->query('date', today()->format('Y-m-d'));

        return view('admin.cash.index', [
            'date' => $date, 'summary' => $this->finance->cashSummary($date),
            'closing' => CashClosing::whereDate('date', $date)->first(),
            'closings' => CashClosing::latest('date')->limit(30)->get(),
            'denominations' => CashClosing::DENOMINATIONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'], 'denominations' => ['nullable', 'array'], 'denominations.*' => ['nullable', 'integer', 'min:0'],
            'other' => ['nullable', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:200'],
        ]);
        $closing = $this->finance->closeCash($data, $request->user());

        return redirect()->route('admin.cash.show', $closing)->with('ok', 'Kas '.$closing->date->format('d/m/Y').' ditutup. Selisih: '.\App\Support\Rupiah::format($closing->difference).'.');
    }

    public function show(CashClosing $closing)
    {
        return view('admin.cash.show', ['closing' => $closing->load('journals.lines.account', 'user'), 'denominations' => CashClosing::DENOMINATIONS]);
    }
}
