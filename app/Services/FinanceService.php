<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CashClosing;
use App\Models\DepreciationRun;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Biaya & kas masuk/keluar, jurnal manual, tutup kas harian, aset tetap dan penyusutan. */
class FinanceService
{
    public function __construct(private JournalService $journals) {}

    // ------------------------------------------------------- Biaya dan kas masuk lain

    /** @param  array<string, mixed>  $data  kind: expense|income, via: cash|bank, account_id, amount */
    public function cashEntry(array $data, ?User $user = null): Journal
    {
        $kind = ($data['kind'] ?? 'expense') === 'income' ? 'income' : 'expense';
        $via = ($data['via'] ?? 'cash') === 'bank' ? 'bank' : 'cash';
        $amount = (int) ($data['amount'] ?? 0);
        $account = Account::find($data['account_id'] ?? null);

        if ($amount <= 0 || ! $account) {
            throw ValidationException::withMessages(['amount' => 'Pilih akun dan isi jumlah lebih dari 0.']);
        }
        if (in_array($account->key, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages(['account_id' => 'Akun lawan tidak boleh Kas/Bank.']);
        }

        $date = Carbon::parse($data['date'] ?? today());
        $text = trim((string) ($data['description'] ?? '')) ?: $account->name;

        return $this->journals->post(
            $kind,
            ($kind === 'expense' ? 'Biaya: ' : 'Kas masuk: ').$text,
            $kind === 'expense'
                ? [['account_id' => $account->id, 'debit' => $amount, 'memo' => $text], ['account' => $via, 'credit' => $amount]]
                : [['account' => $via, 'debit' => $amount], ['account_id' => $account->id, 'credit' => $amount, 'memo' => $text]],
            null, $date, $user?->id,
        );
    }

    // --------------------------------------------------------------- Jurnal manual

    /** @param  array<string, mixed>  $data  lines: [{account_id, debit, credit, memo}] */
    public function manualJournal(array $data, ?User $user = null): Journal
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $row) {
            $debit = (int) ($row['debit'] ?? 0);
            $credit = (int) ($row['credit'] ?? 0);
            if (empty($row['account_id']) || ($debit === 0 && $credit === 0)) {
                continue;
            }
            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages(['lines' => 'Satu baris hanya boleh diisi debit ATAU kredit.']);
            }
            $lines[] = ['account_id' => (int) $row['account_id'], 'debit' => $debit, 'credit' => $credit, 'memo' => $row['memo'] ?? null];
        }

        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'Jurnal butuh minimal dua baris akun.']);
        }

        $debit = array_sum(array_column($lines, 'debit'));
        $credit = array_sum(array_column($lines, 'credit'));
        if ($debit !== $credit) {
            throw ValidationException::withMessages(['lines' => 'Jurnal tidak seimbang: debit '.number_format($debit, 0, ',', '.').' ≠ kredit '.number_format($credit, 0, ',', '.').'.']);
        }

        if (Account::whereIn('id', array_column($lines, 'account_id'))->where('is_active', true)->count() !== count(array_unique(array_column($lines, 'account_id')))) {
            throw ValidationException::withMessages(['lines' => 'Ada akun yang tidak ditemukan atau nonaktif.']);
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            throw ValidationException::withMessages(['description' => 'Isi keterangan jurnal.']);
        }

        return $this->journals->post('manual', $description, $lines, null, Carbon::parse($data['date'] ?? today()), $user?->id);
    }

    // ----------------------------------------------------------------- Kas harian

    /** Ringkasan kas sistem untuk satu hari: saldo awal, masuk, keluar, dan seharusnya ada di laci. */
    public function cashSummary(string $date): array
    {
        $account = Account::where('key', 'cash')->firstOrFail();
        $before = $account->netDebit(null, Carbon::parse($date)->subDay()->format('Y-m-d').' 23:59:59');

        $day = DB::table('journal_lines')->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->where('journal_lines.account_id', $account->id)->whereDate('journals.date', $date)
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')->first();

        return [
            'opening' => $before, 'in' => (int) $day->d, 'out' => (int) $day->c,
            'expected' => $before + (int) $day->d - (int) $day->c,
        ];
    }

    /** @param  array<string, mixed>  $data  denominations: [pecahan => jumlah lembar], extra: uang receh lain */
    public function closeCash(array $data, ?User $user = null): CashClosing
    {
        $date = Carbon::parse($data['date'] ?? today())->startOfDay();

        if (CashClosing::whereDate('date', $date)->exists()) {
            throw ValidationException::withMessages(['date' => 'Kas tanggal '.$date->format('d/m/Y').' sudah ditutup.']);
        }

        $denominations = [];
        $counted = 0;
        foreach (CashClosing::DENOMINATIONS as $value) {
            $count = max(0, (int) ($data['denominations'][$value] ?? 0));
            $denominations[$value] = $count;
            $counted += $value * $count;
        }
        $counted += max(0, (int) ($data['other'] ?? 0));

        return DB::transaction(function () use ($data, $user, $date, $denominations, $counted) {
            $summary = $this->cashSummary($date->format('Y-m-d'));
            $diff = $counted - $summary['expected'];

            $closing = CashClosing::create([
                'date' => $date, 'opening' => $summary['opening'], 'cash_in' => $summary['in'], 'cash_out' => $summary['out'],
                'expected' => $summary['expected'], 'counted' => $counted, 'difference' => $diff,
                'denominations' => $denominations, 'note' => $data['note'] ?? null, 'user_id' => $user?->id,
            ]);
            $closing->forceFill(['number' => CostingService::number('KH', $date, $closing->id)])->save();

            $this->journals->post('cash_close', 'Tutup kas '.$closing->number.($diff === 0 ? ' (pas)' : ($diff > 0 ? ' (lebih)' : ' (kurang)')), [
                ['account' => 'cash', 'debit' => max($diff, 0)],
                ['account' => 'cash_over_short', 'credit' => max($diff, 0)],
                ['account' => 'cash_over_short', 'debit' => max(-$diff, 0)],
                ['account' => 'cash', 'credit' => max(-$diff, 0)],
            ], $closing, $date, $user?->id);

            return $closing;
        });
    }

    // ---------------------------------------------------------------- Aset tetap

    /** @param  array<string, mixed>  $data  paid_via: cash|bank|payable|equity */
    public function addAsset(array $data, ?User $user = null): FixedAsset
    {
        $cost = (int) ($data['cost'] ?? 0);
        $salvage = (int) ($data['salvage'] ?? 0);
        $life = (int) ($data['life_months'] ?? 0);
        $via = $data['paid_via'] ?? 'cash';

        if ($cost <= 0 || $life <= 0 || $salvage < 0 || $salvage >= $cost) {
            throw ValidationException::withMessages(['cost' => 'Harga perolehan harus > 0, umur > 0, dan nilai sisa lebih kecil dari harga perolehan.']);
        }
        if (! in_array($via, ['cash', 'bank', 'payable', 'equity'], true)) {
            throw ValidationException::withMessages(['paid_via' => 'Sumber dana tidak dikenal.']);
        }

        return DB::transaction(function () use ($data, $user, $cost, $salvage, $life, $via) {
            $count = FixedAsset::count() + 1;
            $code = sprintf('AT-%03d', $count);
            while (FixedAsset::where('code', $code)->exists()) {
                $code = sprintf('AT-%03d', ++$count);
            }

            $asset = FixedAsset::create([
                'code' => $code, 'name' => $data['name'], 'acquired_on' => Carbon::parse($data['acquired_on'] ?? today()),
                'cost' => $cost, 'salvage' => $salvage, 'life_months' => $life, 'note' => $data['note'] ?? null, 'user_id' => $user?->id,
            ]);

            $this->journals->post('asset', 'Perolehan aset '.$asset->code.' '.$asset->name, [
                ['account' => 'fixed_assets', 'debit' => $cost],
                ['account' => $via, 'credit' => $cost],
            ], $asset, $asset->acquired_on, $user?->id);

            return $asset;
        });
    }

    /**
     * Hitung penyusutan garis lurus sampai bulan $period (YYYY-MM). Aman diulang: bulan yang sudah dihitung dilewati,
     * dan bulan-bulan yang terlewat ikut dikejar.
     *
     * @return array{runs: int, amount: int}
     */
    public function depreciate(string $period, ?User $user = null): array
    {
        $end = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
        $runs = 0;
        $total = 0;

        DB::transaction(function () use ($end, $user, &$runs, &$total) {
            foreach (FixedAsset::where('status', 'active')->orderBy('id')->lockForUpdate()->get() as $asset) {
                $month = $asset->acquired_on->copy()->startOfMonth();

                while ($month->lte($end) && $asset->depreciated < $asset->depreciable()) {
                    $key = $month->format('Y-m');

                    if (! DepreciationRun::where('fixed_asset_id', $asset->id)->where('period', $key)->exists()) {
                        $amount = min($asset->monthly(), $asset->depreciable() - $asset->depreciated);
                        // Bulan terakhir menyerap sisa pembulatan.
                        $remainingMonths = $asset->life_months - $asset->runs()->count();
                        if ($remainingMonths <= 1) {
                            $amount = $asset->depreciable() - $asset->depreciated;
                        }

                        if ($amount > 0) {
                            DepreciationRun::create(['fixed_asset_id' => $asset->id, 'period' => $key, 'amount' => $amount]);
                            $asset->depreciated += $amount;
                            $asset->save();

                            $this->journals->post('depreciation', 'Penyusutan '.$asset->code.' '.$asset->name.' '.$key, [
                                ['account' => 'depreciation_expense', 'debit' => $amount],
                                ['account' => 'accumulated_depreciation', 'credit' => $amount],
                            ], $asset, $month->copy()->endOfMonth()->min(now()), $user?->id);

                            $runs++;
                            $total += $amount;
                        }
                    }

                    $month->addMonth();
                }
            }
        });

        return ['runs' => $runs, 'amount' => $total];
    }

    /** Lepas/jual aset: hapus nilai buku, catat hasil penjualan, dan untung/rugi. */
    public function disposeAsset(FixedAsset $asset, int $proceeds, string $via, ?string $date = null, ?User $user = null): FixedAsset
    {
        if ($asset->status !== 'active') {
            throw ValidationException::withMessages(['asset' => 'Aset sudah dilepas.']);
        }
        if ($proceeds < 0 || ! in_array($via, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages(['proceeds' => 'Hasil penjualan tidak valid.']);
        }

        return DB::transaction(function () use ($asset, $proceeds, $via, $date, $user) {
            $book = $asset->bookValue();
            $gain = $proceeds - $book;

            $this->journals->post('asset', 'Pelepasan aset '.$asset->code.' '.$asset->name, [
                ['account' => $via, 'debit' => $proceeds],
                ['account' => 'accumulated_depreciation', 'debit' => $asset->depreciated],
                ['account' => 'operating_expense', 'debit' => max(-$gain, 0), 'memo' => 'Rugi pelepasan aset'],
                ['account' => 'fixed_assets', 'credit' => $asset->cost],
                ['account' => 'other_income', 'credit' => max($gain, 0), 'memo' => 'Laba pelepasan aset'],
            ], $asset, Carbon::parse($date ?? today()), $user?->id);

            $asset->forceFill(['status' => 'disposed'])->save();

            return $asset;
        });
    }
}
