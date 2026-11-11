<?php

namespace App\Domain\Billing\DTOs;

use App\Domain\Core\Enums\TaxType;

/**
 * Resultado do cálculo fiscal de uma linha de documento.
 */
final readonly class LineCalculation
{
    public function __construct(
        public float $grossAmount,
        public float $discountAmount,
        public float $netAmount,
        public TaxType $taxType,
        public float $ivaRate,
        public float $ivaAmount,
        public bool $retentionApplicable,
        public float $retentionRate,
        public float $retentionAmount,
        public float $totalLine,
        public ?string $exemptionCode = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'gross_amount'         => $this->grossAmount,
            'discount_amount'      => $this->discountAmount,
            'net_amount'           => $this->netAmount,
            'tax_type'             => $this->taxType->value,
            'iva_rate'             => $this->ivaRate,
            'iva_amount'           => $this->ivaAmount,
            'retention_applicable' => $this->retentionApplicable,
            'retention_rate'       => $this->retentionRate,
            'retention_amount'     => $this->retentionAmount,
            'total_line'           => $this->totalLine,
            'exemption_code'       => $this->exemptionCode,
        ];
    }
}
