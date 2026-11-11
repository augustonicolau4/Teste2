<?php

namespace App\Domain\Finance\Enums;

/**
 * Estado da sessão de caixa (abertura/fecho).
 */
enum CashSessionStatus: string
{
    case ABERTA  = 'open';
    case FECHADA = 'closed';

    public function label(): string
    {
        return $this === self::ABERTA ? 'Aberta' : 'Fechada';
    }
}
