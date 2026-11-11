<?php

namespace App\Domain\Core\Enums;

/**
 * Tipos de documentos fiscais emitidos pelo autogest_nicola.
 */
enum DocumentType: string
{
    case FATURA            = 'FT'; // Fatura
    case FATURA_RECIBO     = 'FR'; // Fatura-Recibo
    case FATURA_PROFORMA   = 'FP'; // Fatura Pró-forma
    case RECIBO            = 'RC'; // Recibo
    case NOTA_CREDITO      = 'NC'; // Nota de Crédito
    case NOTA_DEBITO       = 'ND'; // Nota de Débito
    case GUIA_REMESSA      = 'GR'; // Guia de Remessa

    public function label(): string
    {
        return match ($this) {
            self::FATURA          => 'Fatura',
            self::FATURA_RECIBO   => 'Fatura-Recibo',
            self::FATURA_PROFORMA => 'Fatura Pró-forma',
            self::RECIBO          => 'Recibo',
            self::NOTA_CREDITO    => 'Nota de Crédito',
            self::NOTA_DEBITO     => 'Nota de Débito',
            self::GUIA_REMESSA    => 'Guia de Remessa',
        };
    }

    /**
     * Código de tipo de documento usado no SAF-T AO.
     */
    public function safTCode(): string
    {
        return match ($this) {
            self::FATURA, self::FATURA_RECIBO => 'FT',
            self::FATURA_PROFORMA             => 'FP',
            self::RECIBO                      => 'RC',
            self::NOTA_CREDITO                => 'NC',
            self::NOTA_DEBITO                 => 'ND',
            self::GUIA_REMESSA                => 'GR',
        };
    }

    /**
     * Documentos que constituem obrigação fiscal (entram no SAF-T e geram IVA/retenção).
     */
    public function isFiscal(): bool
    {
        return match ($this) {
            self::FATURA_PROFORMA => false, // Pro-forma não é documento fiscal
            default               => true,
        };
    }

    /**
     * Documentos que geram saída de stock.
     */
    public function movesStock(): bool
    {
        return in_array($this, [self::FATURA, self::FATURA_RECIBO, self::GUIA_REMESSA], true);
    }

    /**
     * Documentos que representam liquidação (pagamento) do cliente.
     */
    public function isReceipt(): bool
    {
        return in_array($this, [self::RECIBO, self::FATURA_RECIBO], true);
    }

    /**
     * Documento que pode ser anulado por Nota de Crédito.
     */
    public function canBeCredited(): bool
    {
        return in_array($this, [self::FATURA, self::FATURA_RECIBO], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $t) => [$t->value => $t->label()]
        )->all();
    }
}
