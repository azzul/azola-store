<?php

namespace App\Services;

use App\Exceptions\UnbalancedJournalException;
use App\Models\Account;
use App\Models\Journal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Satu-satunya tempat jurnal dibentuk. Web, kasir desktop, dan Android memanggil
 * service yang sama (lewat OrderService/InventoryService) sehingga hasil jurnalnya pasti seragam.
 */
class JournalService
{
    /**
     * @param  array<int, array{account?: string, account_id?: int, debit?: int, credit?: int, memo?: string}>  $lines
     * @param  array<string, mixed>  $extra  kolom tambahan, mis. reversal_of_id
     */
    public function post(
        string $type,
        string $description,
        array $lines,
        ?Model $source = null,
        ?CarbonInterface $date = null,
        ?int $userId = null,
        array $extra = [],
    ): ?Journal {
        $lines = array_values(array_filter(
            $lines,
            fn ($line) => (int) ($line['debit'] ?? 0) !== 0 || (int) ($line['credit'] ?? 0) !== 0,
        ));

        if ($lines === []) {
            return null;
        }

        $debit = 0;
        $credit = 0;
        foreach ($lines as $line) {
            $d = (int) ($line['debit'] ?? 0);
            $c = (int) ($line['credit'] ?? 0);

            if ($d < 0 || $c < 0 || ($d > 0 && $c > 0)) {
                throw new InvalidArgumentException('Baris jurnal harus berisi debit ATAU kredit bernilai positif.');
            }

            $debit += $d;
            $credit += $c;
        }

        if ($debit !== $credit) {
            throw new UnbalancedJournalException($debit, $credit);
        }

        return DB::transaction(function () use ($type, $description, $lines, $source, $date, $userId, $extra, $debit) {
            $date ??= now();

            $keys = array_values(array_unique(array_filter(array_column($lines, 'account'))));
            $ids = Account::whereIn('key', $keys)->pluck('id', 'key');

            foreach ($keys as $key) {
                if (! isset($ids[$key])) {
                    throw new RuntimeException("Akun \"{$key}\" belum ada. Jalankan: php artisan db:seed --class=AccountSeeder");
                }
            }

            $journal = Journal::create($extra + [
                'date' => $date->toDateString(),
                'type' => $type,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'user_id' => $userId,
                'total' => $debit,
            ]);

            $journal->number = sprintf('JRN-%s-%06d', $date->format('ym'), $journal->id);
            $journal->save();

            foreach ($lines as $line) {
                $journal->lines()->create([
                    'account_id' => $line['account_id'] ?? $ids[$line['account']],
                    'debit' => (int) ($line['debit'] ?? 0),
                    'credit' => (int) ($line['credit'] ?? 0),
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $journal->load('lines.account');
        });
    }

    /** Buat jurnal pembalik (debit<->kredit). Aman dipanggil dua kali: yang kedua mengembalikan jurnal balik yang sama. */
    public function reverse(Journal $journal, ?string $reason = null, ?int $userId = null): Journal
    {
        return DB::transaction(function () use ($journal, $reason, $userId) {
            $fresh = Journal::whereKey($journal->id)->lockForUpdate()->firstOrFail();

            if ($fresh->reversed_by_id) {
                return Journal::findOrFail($fresh->reversed_by_id);
            }

            if ($fresh->reversal_of_id) {
                throw new RuntimeException('Jurnal pembalik tidak boleh dibalik lagi.');
            }

            $lines = $fresh->lines()->get()->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'memo' => $line->memo,
            ])->all();

            $reversal = $this->post(
                'reversal',
                'Pembalik '.$fresh->number.($reason ? ': '.$reason : ''),
                $lines,
                $fresh->source,
                now(),
                $userId,
                ['reversal_of_id' => $fresh->id],
            );

            $fresh->forceFill(['reversed_by_id' => $reversal->id])->save();

            return $reversal;
        });
    }

    /** Balik semua jurnal aktif milik satu sumber (mis. satu pesanan). */
    public function reverseForSource(Model $source, ?string $reason = null, ?int $userId = null): int
    {
        $journals = Journal::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('reversal_of_id')
            ->whereNull('reversed_by_id')
            ->orderBy('id')
            ->get();

        foreach ($journals as $journal) {
            $this->reverse($journal, $reason, $userId);
        }

        return $journals->count();
    }
}
