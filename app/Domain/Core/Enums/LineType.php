<?php

namespace App\Domain\Core\Enums;

/**
 * Natureza da linha do documento.
 */
enum LineType: string
{
    case PRODUCT = 'product'; // Peça / artigo
    case SERVICE = 'service'; // Serviço / mão de obra
    case OTHER   = 'other';   // Outros (encargos, descontos)

    public function label(): string
    {
        return match ($this) {
            self::PRODUCT => 'Peça / Produto',
            self::SERVICE => 'Serviço / Mão de Obra',
            self::OTHER   => 'Outro',
        };
    }

    /**
     * Apenas linhas de serviço estão sujeitas a Retenção na Fonte (6,5%).
     */
    public function isRetentionApplicable(): bool
    {
        return in_array(
            $this->value,
            config('autogest.fiscal.retention_applies_to', ['service']),
            true
        );
    }
}
