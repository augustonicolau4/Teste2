<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTOs\DocumentData;
use App\Domain\Billing\Models\Document;
use App\Domain\Billing\Services\DigitalSignature;
use App\Domain\Billing\Services\DocumentNumbering;
use App\Domain\Billing\Services\HashChain;
use App\Domain\Billing\Services\QrCodeGenerator;
use App\Domain\Billing\Services\TaxCalculator;
use App\Domain\Core\Enums\DocumentStatus;
use App\Domain\Core\Enums\LineType;
use App\Domain\Core\Enums\PaymentMethod;
use App\Domain\Finance\Models\LedgerEntry;
use App\Domain\Finance\Models\Payment;
use App\Domain\HR\Services\CommissionService;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Emissão de um documento fiscal completo:
 * numeração -> cálculo -> persistência -> hash encadeado -> assinatura RSA -> QR -> stock/pagamentos/comissões.
 */
class IssueDocumentAction
{
    public function __construct(
        private readonly TaxCalculator $calculator,
        private readonly DocumentNumbering $numbering,
        private readonly HashChain $hashChain,
        private readonly DigitalSignature $signature,
        private readonly QrCodeGenerator $qr,
        private readonly StockService $stock,
        private readonly CommissionService $commissions,
    ) {
    }

    public function execute(DocumentData $data): Document
    {
        if (empty($data->lines)) {
            throw new InvalidArgumentException('O documento tem de ter pelo menos uma linha.');
        }

        return DB::transaction(function () use ($data) {
            $issueDate = $data->issueDate ? CarbonImmutable::parse($data->issueDate) : CarbonImmutable::now();
            $alloc = $this->numbering->next(
                $data->documentType,
                $data->companyId,
                (int) $issueDate->year,
                $data->branchId,
            );

            // 1. Cálculo fiscal das linhas e totais
            $calculations = [];
            foreach ($data->lines as $line) {
                $calculations[] = $this->calculator->calculateLine($line);
            }
            $totals = $this->calculator->calculateDocument($calculations);

            // 2. Documento
            $document = Document::query()->create([
                'company_id'           => $data->companyId,
                'branch_id'            => $data->branchId,
                'series_id'            => $alloc['series']->id,
                'document_type'        => $data->documentType->value,
                'sequence'             => $alloc['sequence'],
                'number'               => $alloc['number'],
                'customer_id'          => $data->customerId,
                'vehicle_id'           => $data->vehicleId,
                'service_order_id'     => $data->serviceOrderId,
                'user_id'              => $data->userId,
                'promoter_id'          => $data->promoterId,
                'status'               => DocumentStatus::EMITIDO->value,
                'is_fiscal'            => $data->documentType->isFiscal(),
                'issue_date'           => $issueDate->toDateString(),
                'due_date'             => $data->dueDate,
                'system_entry_date'    => now(),
                'gross_total'          => $totals->grossTotal,
                'discount_total'       => $totals->discountTotal,
                'net_total'            => $totals->netTotal,
                'iva_total'            => $totals->ivaTotal,
                'retention_total'      => $totals->retentionTotal,
                'grand_total'          => $totals->grandTotal,
                'balance'              => $totals->payableTotal,
                'notes'                => $data->notes,
                'software_certificate' => config('autogest.fiscal.software_certificate_number'),
            ]);

            // 3. Linhas
            foreach ($data->lines as $index => $line) {
                $calc = $calculations[$index];
                $document->lines()->create([
                    'line_number'          => $index + 1,
                    'line_type'            => $line['line_type'] ?? LineType::SERVICE->value,
                    'product_id'           => $line['product_id'] ?? null,
                    'service_order_item_id' => $line['service_order_item_id'] ?? null,
                    'description'          => $line['description'],
                    'unit_code'            => $line['unit_code'] ?? null,
                    'quantity'             => $line['quantity'],
                    'unit_price'           => $line['unit_price'],
                    'discount_rate'        => $line['discount_rate'] ?? 0,
                    'discount_amount'      => $calc->discountAmount,
                    'gross_amount'         => $calc->grossAmount,
                    'net_amount'           => $calc->netAmount,
                    'tax_type'             => $calc->taxType->value,
                    'iva_rate'             => $calc->ivaRate,
                    'iva_amount'           => $calc->ivaAmount,
                    'exemption_code'       => $calc->exemptionCode,
                    'retention_applicable' => $calc->retentionApplicable,
                    'retention_rate'       => $calc->retentionRate,
                    'retention_amount'     => $calc->retentionAmount,
                    'total_line'           => $calc->totalLine,
                    'notes'                => $line['notes'] ?? null,
                ]);
            }

            // 4. Resumo de impostos
            foreach ($totals->taxSummary as $tax) {
                $document->taxes()->create([
                    'tax_type'       => $tax['tax_type'],
                    'rate'           => $tax['rate'],
                    'base_amount'    => $tax['base_amount'],
                    'amount'         => $tax['amount'],
                    'exemption_code' => $tax['exemption_code'],
                    'country_region' => config('autogest.saft.tax_country', 'AO'),
                ]);
            }

            // 5. Hash encadeado + assinatura digital (apenas documentos fiscais)
            if ($document->is_fiscal) {
                $previous = $this->hashChain->lastHash($data->companyId, $document->uuid);

                $hash = $this->hashChain->compute(
                    $issueDate->toDateString(),
                    $document->system_entry_date->format('Y-m-d\TH:i:s'),
                    $document->number,
                    (float) $totals->grandTotal,
                    '1',
                    $previous,
                );

                $document->hash = $hash;
                $document->hash_control = $previous;
                $document->signature = $this->signature->sign($hash);
                $document->signature_algorithm = 'RSA-SHA256';
                $document->save();

                $document->signatures()->create([
                    'signature_number'     => 1,
                    'hash'                 => $hash,
                    'previous_hash'        => $previous,
                    'signature'            => $document->signature,
                    'algorithm'            => 'RSA-SHA256',
                    'certificate_number'   => $document->software_certificate,
                    'software_certificate' => config('autogest.fiscal.software_certificate_number'),
                    'signed_at'            => now(),
                ]);
            }

            // 6. QR Code
            $document->qr_payload = $document->qrPayload();
            $document->save();

            // 7. Baixa de stock
            if ($data->deductStock && $data->documentType->movesStock()) {
                $this->deductStock($document);
            }

            // 8. Conta corrente do cliente (débito da fatura)
            $this->postLedger($document);

            // 9. Pagamentos (Fatura-Recibo / Recibo) - crédito na conta corrente
            $this->registerPayments($document, $data);

            // 10. Comissão de angariação (promotor)
            if ($document->is_fiscal) {
                $this->commissions->forDocument($document);
            }

            return $document->fresh(['lines', 'taxes', 'signatures', 'customer', 'vehicle', 'series']);
        });
    }

    private function deductStock(Document $document): void
    {
        foreach ($document->lines as $line) {
            if (! $line->product_id) {
                continue;
            }

            $product = Product::query()->find($line->product_id);
            if (! $product || ! $product->track_stock) {
                continue;
            }

            $this->stock->exit($document->company_id, $product, (float) $line->quantity, [
                'source'   => $document,
                'user_id'  => $document->user_id,
                'reason'   => "Baixa por {$document->number}",
                'unit_cost' => $product->cost_price,
            ]);
        }
    }

    private function registerPayments(Document $document, DocumentData $data): void
    {
        if (empty($data->payments)) {
            return;
        }

        $paid = 0.0;
        foreach ($data->payments as $payment) {
            $method = PaymentMethod::tryFrom($payment['method']) ?? PaymentMethod::NUMERARIO;
            $amount = round((float) $payment['amount'], 2);

            Payment::query()->create([
                'company_id'  => $document->company_id,
                'document_id' => $document->id,
                'customer_id' => $document->customer_id,
                'user_id'     => $data->userId,
                'direction'   => 'in',
                'method'      => $method->value,
                'amount'      => $amount,
                'reference'   => $payment['reference'] ?? null,
                'paid_at'     => now(),
            ]);

            // Crédito na conta-corrente do cliente
            if ($document->customer_id) {
                $this->postPaymentLedger($document, $amount, $method);
            }

            $paid += $amount;
        }

        $document->paid_total = round($paid, 2);
        $document->balance = round((float) $document->balance - $paid, 2);

        $document->status = $document->balance <= 0
            ? DocumentStatus::PAGO->value
            : DocumentStatus::PARCIAL->value;

        $document->save();
    }

    private function postLedger(Document $document): void
    {
        if (! $document->customer_id || ! $document->is_fiscal) {
            return;
        }

        // Valor a receber = total bruto - retenção na fonte (retida pelo cliente e
        // entregue à AGT em nome da empresa).
        $payable = round((float) $document->grand_total - (float) $document->retention_total, 2);

        $previous = $this->currentCustomerBalance($document->company_id, $document->customer_id);
        $balanceAfter = round($previous + $payable, 2);

        LedgerEntry::query()->create([
            'company_id'    => $document->company_id,
            'account_type'  => 'customer',
            'account_id'    => $document->customer_id,
            'document_id'   => $document->id,
            'entry_type'    => 'debit',
            'amount'        => $payable,
            'balance_after' => $balanceAfter,
            'description'   => "Emissão {$document->number}",
            'entry_date'    => $document->issue_date,
        ]);

        $document->customer()->update(['balance' => $balanceAfter]);
    }

    private function postPaymentLedger(Document $document, float $amount, PaymentMethod $method): void
    {
        $previous = $this->currentCustomerBalance($document->company_id, $document->customer_id);
        $balanceAfter = round($previous - $amount, 2);

        LedgerEntry::query()->create([
            'company_id'    => $document->company_id,
            'account_type'  => 'customer',
            'account_id'    => $document->customer_id,
            'document_id'   => $document->id,
            'entry_type'    => 'credit',
            'amount'        => $amount,
            'balance_after' => $balanceAfter,
            'description'   => "Recebimento {$document->number} ({$method->label()})",
            'entry_date'    => $document->issue_date,
        ]);

        $document->customer()->update(['balance' => $balanceAfter]);
    }

    private function currentCustomerBalance(int $companyId, int $customerId): float
    {
        return (float) LedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('account_type', 'customer')
            ->where('account_id', $customerId)
            ->orderByDesc('id')
            ->value('balance_after');
    }
}
