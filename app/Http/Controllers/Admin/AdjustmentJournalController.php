<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Journal;
use App\Services\FinanceService;
use App\Services\JournalService;
use Illuminate\Http\Request;

/** Jurnal penyesuaian: jurnal manual (koreksi, akrual) beserta jurnal penyesuaian yang dibuat sistem (stok, kas, penyusutan). */
class AdjustmentJournalController extends Controller
{
    public function __construct(private FinanceService $finance, private JournalService $journals) {}

    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', today()->format('Y-m-d'));

        $journals = Journal::with('lines.account')->whereIn('type', ['manual', 'adjustment', 'depreciation', 'cash_close'])
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->filled('type'), fn ($w) => $w->where('type', $request->query('type')))
            ->latest('date')->latest('id')->paginate(30)->withQueryString();

        return view('admin.adjustment-journals.index', ['journals' => $journals, 'from' => $from, 'to' => $to]);
    }

    public function create()
    {
        return view('admin.adjustment-journals.form', ['accounts' => Account::where('is_active', true)->orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'], 'description' => ['required', 'string', 'max:200'],
            'lines' => ['required', 'array', 'min:2'], 'lines.*.account_id' => ['nullable', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'integer', 'min:0'], 'lines.*.credit' => ['nullable', 'integer', 'min:0'], 'lines.*.memo' => ['nullable', 'string', 'max:200'],
        ]);
        $journal = $this->finance->manualJournal($data, $request->user());

        return redirect()->route('admin.journals.show', $journal)->with('ok', 'Jurnal penyesuaian '.$journal->number.' diposting.');
    }

    public function cancel(Request $request, Journal $journal)
    {
        abort_unless($journal->type === 'manual', 404);
        $this->journals->reverse($journal, 'dibatalkan', $request->user()->id, true);

        return back()->with('ok', 'Jurnal dibalik pada tanggal yang sama.');
    }
}
