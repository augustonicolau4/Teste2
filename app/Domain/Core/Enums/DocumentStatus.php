<?php

namespace App\Domain\Core\Enums;

enum DocumentStatus: string
{
    case RASCUNHO   = 'draft';       // Rascunho (não emitido)
    case EMITIDO    = 'issued';      // Emitido e assinado
    case PAGO       = 'paid';        // Totalmente liquidado
    case PARCIAL    = 'partial';     // Parcialmente liquidado
    case ANULADO    = 'cancelled';   // Anulado (por NC)
    case VENCIDO    = 'overdue';     // Vencido

    public function label(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Rascunho',
            self::EMITIDO  => 'Emitido',
            self::PAGO     => 'Pago',
            self::PARCIAL  => 'Parcial',
            self::ANULADO  => 'Anulado',
            self::VENCIDO  => 'Vencido',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PAGO, self::ANULADO], true);
    }

    public function canBeEdited(): bool
    {
        return $this === self::RASCUNHO;
    }
}
