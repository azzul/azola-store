<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public const TYPES = [
        'asset' => ['Aset', 'debit'], 'liability' => ['Kewajiban', 'credit'], 'equity' => ['Ekuitas', 'credit'],
        'revenue' => ['Pendapatan', 'credit'], 'cogs' => ['Harga pokok', 'debit'], 'expense' => ['Beban', 'debit'],
    ];

    public function index(Request $request)
    {
        $balances = \Illuminate\Support\Facades\DB::table('journal_lines')
            ->selectRaw('account_id, SUM(debit) as d, SUM(credit) as c, COUNT(*) as n')->groupBy('account_id')->get()->keyBy('account_id');

        return view('admin.accounts.index', [
            'accounts' => Account::orderBy('code')->get(), 'balances' => $balances, 'types' => self::TYPES,
            'edit' => $request->filled('edit') ? Account::find($request->query('edit')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Account::create($data + ['normal_balance' => self::TYPES[$data['type']][1], 'is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.accounts.index')->with('ok', 'Akun ditambahkan.');
    }

    public function update(Request $request, Account $account)
    {
        $data = $this->validated($request, $account);

        // Akun sistem dipakai kode program: tipenya dikunci, kode dan nama boleh disesuaikan.
        if ($account->key || $account->lines()->exists()) {
            unset($data['type']);
        } else {
            $data['normal_balance'] = self::TYPES[$data['type']][1];
        }

        $account->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.accounts.index')->with('ok', 'Akun diperbarui.');
    }

    public function destroy(Account $account)
    {
        if ($account->key || $account->lines()->exists()) {
            return back()->withErrors(['account' => $account->key ? 'Akun sistem tidak bisa dihapus (bisa dinonaktifkan atau diganti namanya).' : 'Akun sudah punya transaksi, jadi tidak bisa dihapus. Nonaktifkan saja.']);
        }
        $account->delete();

        return redirect()->route('admin.accounts.index')->with('ok', 'Akun dihapus.');
    }

    private function validated(Request $request, ?Account $account = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => [$account ? 'nullable' : 'required', Rule::in(array_keys(self::TYPES))],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
    }
}
