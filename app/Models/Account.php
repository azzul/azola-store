<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = ['code', 'key', 'name', 'type', 'normal_balance'];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** Debit - kredit untuk satu akun (positif = saldo debit). */
    public function netDebit(?string $from = null, ?string $to = null): int
    {
        $query = $this->lines()->join('journals', 'journals.id', '=', 'journal_lines.journal_id');

        if ($from) {
            $query->where('journals.date', '>=', $from);
        }
        if ($to) {
            $query->where('journals.date', '<=', $to);
        }

        return (int) $query->sum('journal_lines.debit') - (int) $query->sum('journal_lines.credit');
    }

    /** Saldo menurut sisi normal akun (aset/beban: debit, kewajiban/modal/pendapatan: kredit). */
    public function balance(?string $from = null, ?string $to = null): int
    {
        $net = $this->netDebit($from, $to);

        return $this->normal_balance === 'debit' ? $net : -$net;
    }

    public static function netDebitByKey(string $key): int
    {
        $account = static::where('key', $key)->first();

        return $account ? $account->netDebit() : 0;
    }
}
