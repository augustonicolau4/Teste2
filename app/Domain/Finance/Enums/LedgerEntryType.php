<?php

namespace App\Domain\Finance\Enums;

enum LedgerEntryType: string
{
    case DEBITO  = 'debit';
    case CREDITO = 'credit';

    public function label(): string
    {
        return $this === self::DEBITO ? 'Débito' : 'Crédito';
    }
}
