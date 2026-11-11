<?php

namespace App\Domain\Billing\Enums;

enum InvoiceRelationType: string
{
    case CREDIT    = 'credit';    // Nota de Crédito anula/reduz a fatura referenciada
    case REFERENCE = 'reference'; // Referência simples
    case DELIVERY  = 'delivery';  // Guia de remessa associada

    public function label(): string
    {
        return match ($this) {
            self::CREDIT    => 'Nota de Crédito',
            self::REFERENCE => 'Referência',
            self::DELIVERY  => 'Guia de Remessa',
        };
    }
}
