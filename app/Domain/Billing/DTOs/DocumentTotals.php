<?php

namespace App\Domain\Billing\DTOs;

/**
 * Totais consolidados de um documento fiscal.
 *
 * Modelo de valores:
 *   grossTotal     = soma dos valores brutos das linhas (qty * preço)
 *   discountTotal  = soma dos descontos de linha
 *   netTotal       = base tributável (gross - discount)
 *   ivaTotal       = IVA liquidado
 *   retentionTotal = Retenção na Fonte retida pelo cliente (6,5% s/ serviços)
 *   grandTotal     = netTotal + ivaTotal (valor do documento)
 *   payableTotal   = grandTotal - retentionTotal (valor líquido a receber)
 *   taxSummary     = resumo por taxa (para o quadro de impostos e SAF-T)
 */
final readonly class DocumentTotals
{
    public function __construct(
        public float $grossTotal,
        public float $discountTotal,
        public float $netTotal,
        public float $ivaTotal,
        public float $retentionTotal,
        public float $grandTotal,
        public float $payableTotal,
        /** @var array<int, array{tax_type:string, rate:float, base_amount:float, amount:float, exemption_code:?string}> */
        public array $taxSummary = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'gross_total'     => $this->grossTotal,
            'discount_total'  => $this->discountTotal,
            'net_total'       => $this->netTotal,
            'iva_total'       => $this->ivaTotal,
            'retention_total' => $this->retentionTotal,
            'grand_total'     => $this->grandTotal,
            'payable_total'   => $this->payableTotal,
            'balance'         => $this->payableTotal,
        ];
    }
}
