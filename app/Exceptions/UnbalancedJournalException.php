<?php

namespace App\Exceptions;

use RuntimeException;

class UnbalancedJournalException extends RuntimeException
{
    public function __construct(public readonly int $debit, public readonly int $credit)
    {
        parent::__construct("Jurnal tidak seimbang: debit {$debit}, kredit {$credit}.");
    }
}
