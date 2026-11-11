<?php

namespace App\Domain\Core\Enums;

/**
 * Códigos de isenção / motivo (Tabela de Isenções AGT - SAF-T AO).
 */
enum ExemptionCode: string
{
    case M00 = 'M00'; // Isento (não especificado)
    case M01 = 'M01'; // Isento - Artigo 12.º do Código do IVA
    case M02 = 'M02'; // Isento - Artigo 13.º do Código do IVA
    case M03 = 'M03'; // Exportação
    case M04 = 'M04'; // Isento - Regime especial
    case M05 = 'M05'; // Isento - Diplomatas
    case M10 = 'M10'; // Isento - Zona Franca
    case M11 = 'M11'; // Isento - Regime simplificado
    case M20 = 'M20'; // Não sujeito - fora do âmbito do imposto

    public function label(): string
    {
        return match ($this) {
            self::M00 => 'Isento (não especificado)',
            self::M01 => 'Isento - Artigo 12.º do CIVA',
            self::M02 => 'Isento - Artigo 13.º do CIVA',
            self::M03 => 'Exportação',
            self::M04 => 'Isento - Regime especial',
            self::M05 => 'Isento - Diplomatas',
            self::M10 => 'Isento - Zona Franca',
            self::M11 => 'Isento - Regime simplificado',
            self::M20 => 'Não sujeito - fora do âmbito do imposto',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $c) => [$c->value => "[{$c->value}] {$c->label()}"]
        )->all();
    }
}
