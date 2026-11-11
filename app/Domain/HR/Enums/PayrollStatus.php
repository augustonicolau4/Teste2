<?php

namespace App\Domain\HR\Enums;

enum PayrollStatus: string
{
    case RASCUNHO   = 'draft';
    case PROCESSADO = 'processed';
    case PAGO       = 'paid';
    case CANCELADO  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::RASCUNHO   => 'Rascunho',
            self::PROCESSADO => 'Processado',
            self::PAGO       => 'Pago',
            self::CANCELADO  => 'Cancelado',
        };
    }
}
