<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Services\FinanceService;
use Illuminate\Http\Request;

/** Aset tetap dan depresiasi (garis lurus bulanan). */
class AssetController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    public function index()
    {
        $assets = FixedAsset::orderByRaw("status = 'active' DESC")->orderBy('code')->get();

        return view('admin.assets.index', [
            'assets' => $assets,
            'cost' => (int) $assets->where('status', 'active')->sum('cost'),
            'accumulated' => (int) $assets->where('status', 'active')->sum('depreciated'),
            'recent' => \App\Models\DepreciationRun::with('asset')->latest('id')->limit(15)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'acquired_on' => ['required', 'date', 'before_or_equal:today'],
            'cost' => ['required', 'integer', 'min:1'], 'salvage' => ['nullable', 'integer', 'min:0'], 'life_months' => ['required', 'integer', 'min:1', 'max:600'],
            'paid_via' => ['required', 'in:cash,bank,payable,equity'], 'note' => ['nullable', 'string', 'max:200'],
        ]);
        $asset = $this->finance->addAsset($data, $request->user());

        return back()->with('ok', 'Aset '.$asset->code.' dicatat. Jalankan depresiasi tiap akhir bulan.');
    }

    public function depreciate(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $result = $this->finance->depreciate($data['period'], $request->user());

        return back()->with('ok', $result['runs'] === 0 ? 'Tidak ada penyusutan baru untuk periode ini.' : $result['runs'].' penyusutan dijurnal, total '.\App\Support\Rupiah::format($result['amount']).'.');
    }

    public function dispose(Request $request, FixedAsset $asset)
    {
        $data = $request->validate(['proceeds' => ['required', 'integer', 'min:0'], 'via' => ['required', 'in:cash,bank'], 'date' => ['nullable', 'date', 'before_or_equal:today']]);
        $this->finance->disposeAsset($asset, (int) $data['proceeds'], $data['via'], $data['date'] ?? null, $request->user());

        return back()->with('ok', 'Aset '.$asset->code.' dilepas.');
    }
}
