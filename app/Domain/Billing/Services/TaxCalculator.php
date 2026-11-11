<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\DTOs\DocumentTotals;
use App\Domain\Billing\DTOs\LineCalculation;
use App\Domain\Core\Enums\ExemptionCode;
use App\Domain\Core\Enums\LineType;
use App\Domain\Core\Enums\TaxType;

/**
 * Motor de cálculo fiscal (Angola / AGT).
 *
 * - IVA (14% / 7% / 0%), isenções com código Mxx
 * - Retenção na Fonte (6,5%) apenas sobre linhas de Serviço / Mão de Obra
 */
class TaxCalculator
{
    private int $decimals;

    public function __construct(?int $decimals = null)
    {
        $this->decimals = $decimals ?? (int) config('autogest.fiscal.round_decimals', 2);
    }

    /**
     * Calcula os valores fiscais de uma linha.
     *
     * @param array{
     *   quantity: float|int|string,
     *   unit_price: float|int|string,
     *   discount_rate?: float|int|string,
     *   discount_amount?: float|int|string,
     *   tax_type?: TaxType|string|null,
     *   line_type?: LineType|string|null,
     *   retention_rate?: float|int|string|null,
     *   exemption_code?: ExemptionCode|string|null,
     *   retention_applicable?: bool|null
     * } $line
     */
    public function calculateLine(array $line): LineCalculation
    {
        $taxType   = $this->resolveTaxType($line['tax_type'] ?? null);
        $lineType  = $this->resolveLineType($line['line_type'] ?? LineType::SERVICE);
        $quantity  = (float) ($line['quantity'] ?? 1);
        $unitPrice = (float) ($line['unit_price'] ?? 0);

        $gross = $this->round($quantity * $unitPrice);

        // Desconto: aceita valor absoluto OU percentagem
        $discount = isset($line['discount_amount']) && $line['discount_amount'] !== null
            ? $this->round((float) $line['discount_amount'])
            : $this->round($gross * (float) ($line['discount_rate'] ?? 0));

        $discount = min($discount, $gross);
        $net = $this->round($gross - $discount);

        // IVA
        $ivaRate = $taxType->rate();
        $ivaAmount = $this->round($net * $ivaRate);

        // Retenção na Fonte: só serviços (config retention_applies_to)
        $retentionApplicable = $line['retention_applicable']
            ?? $lineType->isRetentionApplicable();

        $retentionRate = $retentionApplicable
            ? (float) ($line['retention_rate'] ?? config('autogest.fiscal.retention_rate', 0.065))
            : 0.0;

        $retentionAmount = $retentionApplicable
            ? $this->round($net * $retentionRate)
            : 0.0;

        // Valor da linha = base + IVA (retenção é apresentada separadamente)
        $totalLine = $this->round($net + $ivaAmount);

        $exemptionCode = $taxType->expiryRequired()
            ? $this->resolveExemptionCode($line['exemption_code'] ?? null)
            : null;

        return new LineCalculation(
            grossAmount: $gross,
            discountAmount: $discount,
            netAmount: $net,
            taxType: $taxType,
            ivaRate: $ivaRate,
            ivaAmount: $ivaAmount,
            retentionApplicable: $retentionApplicable,
            retentionRate: $retentionRate,
            retentionAmount: $retentionAmount,
            totalLine: $totalLine,
            exemptionCode: $exemptionCode,
        );
    }

    /**
     * Consolida os totais do documento e o resumo de impostos por taxa.
     *
     * @param  iterable<LineCalculation>  $lines
     */
    public function calculateDocument(iterable $lines): DocumentTotals
    {
        $gross = 0.0;
        $discount = 0.0;
        $net = 0.0;
        $iva = 0.0;
        $retention = 0.0;
        $summary = [];

        foreach ($lines as $line) {
            $gross += $line->grossAmount;
            $discount += $line->discountAmount;
            $net += $line->netAmount;
            $iva += $line->ivaAmount;
            $retention += $line->retentionAmount;

            $key = $line->taxType->value . '|' . $line->ivaRate . '|' . ($line->exemptionCode ?? '');
            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'tax_type'       => $line->taxType->value,
                    'rate'           => $line->ivaRate,
                    'base_amount'    => 0.0,
                    'amount'         => 0.0,
                    'exemption_code' => $line->exemptionCode,
                ];
            }
            $summary[$key]['base_amount'] += $line->netAmount;
            $summary[$key]['amount'] += $line->ivaAmount;
        }

        $gross = $this->round($gross);
        $discount = $this->round($discount);
        $net = $this->round($net);
        $iva = $this->round($iva);
        $retention = $this->round($retention);

        $grand = $this->round($net + $iva);
        $payable = $this->round($grand - $retention);

        $summary = array_map(function (array $row) {
            $row['base_amount'] = $this->round($row['base_amount']);
            $row['amount'] = $this->round($row['amount']);

            return $row;
        }, array_values($summary));

        return new DocumentTotals(
            grossTotal: $gross,
            discountTotal: $discount,
            netTotal: $net,
            ivaTotal: $iva,
            retentionTotal: $retention,
            grandTotal: $grand,
            payableTotal: $payable,
            taxSummary: $summary,
        );
    }

    public function round(float $value): float
    {
        return round($value, $this->decimals, PHP_ROUND_HALF_UP);
    }

    private function resolveTaxType(TaxType|string|null $value): TaxType
    {
        if ($value instanceof TaxType) {
            return $value;
        }

        if (is_string($value)) {
            return TaxType::tryFrom($value) ?? TaxType::IVA_NORMAL;
        }

        return TaxType::IVA_NORMAL;
    }

    private function resolveLineType(LineType|string|null $value): LineType
    {
        if ($value instanceof LineType) {
            return $value;
        }

        return is_string($value) ? (LineType::tryFrom($value) ?? LineType::SERVICE) : LineType::SERVICE;
    }

    private function resolveExemptionCode(ExemptionCode|string|null $value): ?string
    {
        if ($value instanceof ExemptionCode) {
            return $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
