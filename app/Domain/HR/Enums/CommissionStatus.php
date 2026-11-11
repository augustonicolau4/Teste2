<?php

namespace App\Domain\HR\Enums;

/**
 * Estado das comissões (técnicos e promotores).
 */
enum CommissionStatus: string
{
    case PENDENTE = 'pending';
    case APROVADA = 'approved';
    case PAGA     = 'paid';
    case ANULADA  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE => 'Pendente',
            self::APROVADA => 'Aprovada',
            self::PAGA     => 'Paga',
            self::ANULADA  => 'Anulada',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $s) => [$s->value => $s->label()]
        )->all();
    }
}
