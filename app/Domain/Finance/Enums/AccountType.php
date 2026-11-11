<?php

namespace App\Domain\Finance\Enums;

/**
 * Tipo de conta na razão (conta-corrente).
 */
enum AccountType: string
{
    case CLIENTE    = 'customer';
    case FORNECEDOR = 'supplier';

    public function label(): string
    {
        return $this === self::CLIENTE ? 'Cliente' : 'Fornecedor';
    }
}
