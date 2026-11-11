<?php

namespace App\Domain\Core\Enums;

/**
 * Meios de pagamento (com código SAF-T AO).
 */
enum PaymentMethod: string
{
    case NUMERARIO     = 'NU'; // Numerário
    case MULTICAIXA    = 'CC'; // Multicaixa / Cartão
    case TPA           = 'TP'; // TPA (Terminal de Pagamento Automático)
    case TRANSFERENCIA = 'TB'; // Transferência bancária
    case CHEQUE        = 'CH'; // Cheque
    case DEPOSITO      = 'DP'; // Depósito
    case OUTRO         = 'OU'; // Outro

    public function label(): string
    {
        return match ($this) {
            self::NUMERARIO     => 'Numerário',
            self::MULTICAIXA    => 'Multicaixa',
            self::TPA           => 'TPA',
            self::TRANSFERENCIA => 'Transferência Bancária',
            self::CHEQUE        => 'Cheque',
            self::DEPOSITO      => 'Depósito',
            self::OUTRO         => 'Outro',
        };
    }

    public function safTCode(): string
    {
        return $this->value;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $m) => [$m->value => $m->label()]
        )->all();
    }
}
