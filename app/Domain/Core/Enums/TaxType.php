<?php

namespace App\Domain\Core\Enums;

/**
 * Regimes de tributação em IVA (Angola / AGT).
 */
enum TaxType: string
{
    case IVA_NORMAL   = 'IVA14';   // IVA Normal 14%
    case IVA_REDUZIDA = 'IVA07';   // IVA Reduzida 7%
    case IVA_ZERO     = 'IVA00';   // IVA 0%
    case ISENTO       = 'ISE';     // Isento de IVA (artigo 12.º do CIVA)
    case NAO_SUJEITO  = 'NS';      // Não sujeito / fora do âmbito

    public function label(): string
    {
        return match ($this) {
            self::IVA_NORMAL   => 'IVA Normal (14%)',
            self::IVA_REDUZIDA => 'IVA Reduzida (7%)',
            self::IVA_ZERO     => 'IVA 0%',
            self::ISENTO       => 'Isento de IVA',
            self::NAO_SUJEITO  => 'Não Sujeito',
        };
    }

    public function rate(): float
    {
        return match ($this) {
            self::IVA_NORMAL   => config('autogest.fiscal.iva_rate', 0.14),
            self::IVA_REDUZIDA => config('autogest.fiscal.iva_reduced_rate', 0.07),
            self::IVA_ZERO     => 0.0,
            self::ISENTO       => 0.0,
            self::NAO_SUJEITO  => 0.0,
        };
    }

    public function appliesIva(): bool
    {
        return in_array($this, [self::IVA_NORMAL, self::IVA_REDUZIDA, self::IVA_ZERO], true);
    }

    public function expiryRequired(): bool
    {
        return $this === self::ISENTO;
    }
}
