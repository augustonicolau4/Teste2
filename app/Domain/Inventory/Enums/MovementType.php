<?php

namespace App\Domain\Inventory\Enums;

enum MovementType: string
{
    case ENTRADA      = 'in';        // Entrada em armazém (compra, devolução)
    case SAIDA        = 'out';       // Saída (consumo em OS, venda)
    case AJUSTE       = 'adjust';    // Ajuste de inventário
    case TRANSFERENCIA = 'transfer'; // Transferência entre armazéns

    public function label(): string
    {
        return match ($this) {
            self::ENTRADA       => 'Entrada',
            self::SAIDA         => 'Saída',
            self::AJUSTE        => 'Ajuste',
            self::TRANSFERENCIA => 'Transferência',
        };
    }

    public function direction(): int
    {
        return match ($this) {
            self::ENTRADA => 1,
            self::SAIDA   => -1,
            self::AJUSTE  => 0, // definido pelo valor assinado
            self::TRANSFERENCIA => 0,
        };
    }
}
