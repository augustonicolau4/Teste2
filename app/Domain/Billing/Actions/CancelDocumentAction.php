<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTOs\DocumentData;
use App\Domain\Billing\Enums\InvoiceRelationType;
use App\Domain\Billing\Models\Document;
use App\Domain\Billing\Models\DocumentRelation;
use App\Domain\Core\Enums\DocumentStatus;
use App\Domain\Core\Enums\DocumentType;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\StockService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anulação de um documento fiscal através da emissão de uma Nota de Crédito (NC)
 * que referencia a fatura original e repõe o stock.
 */
class CancelDocumentAction
{
    public function __construct(
        private readonly IssueDocumentAction $issue,
        private readonly StockService $stock,
    ) {
    }

    public function execute(Document $original, ?string $reason = null, ?int $userId = null): Document
    {
        if (! $original->document_type->canBeCredited()) {
            throw new RuntimeException("O documento {$original->document_type->label()} não pode ser anulado por Nota de Crédito.");
        }

        if ($original->isCancelled()) {
            throw new RuntimeException("O documento {$original->number} já se encontra anulado.");
        }

        return DB::transaction(function () use ($original, $reason, $userId) {
            $lines = $original->lines->map(fn ($line) => [
                'description'           => $line->description,
                'quantity'              => -1 * (float) $line->quantity, // NC: quantidades negativas
                'unit_price'            => (float) $line->unit_price,
                'line_type'             => $line->line_type->value,
                'tax_type'              => $line->tax_type->value,
                'discount_rate'         => (float) $line->discount_rate,
                'product_id'            => $line->product_id,
                'unit_code'             => $line->unit_code,
                'retention_rate'        => (float) $line->retention_rate,
                'exemption_code'        => $line->exemption_code,
            ])->all();

            $note = $this->issue->execute(new DocumentData(
                companyId: $original->company_id,
                documentType: DocumentType::NOTA_CREDITO,
                lines: $lines,
                customerId: $original->customer_id,
                vehicleId: $original->vehicle_id,
                branchId: $original->branch_id,
                serviceOrderId: $original->service_order_id,
                userId: $userId ?? $original->user_id,
                promoterId: $original->promoter_id,
                notes: 'Anulação de ' . $original->number . ($reason ? " | {$reason}" : ''),
                deductStock: false, // o stock é reposto explicitamente abaixo
            ));

            // Relação NC -> Fatura
            DocumentRelation::query()->create([
                'source_document_id' => $note->id,
                'target_document_id' => $original->id,
                'relation_type'      => InvoiceRelationType::CREDIT->value,
                'amount'             => abs((float) $note->grand_total),
                'reason'             => $reason,
            ]);

            // Reposição de stock
            foreach ($original->lines as $line) {
                if (! $line->product_id || ! $original->document_type->movesStock()) {
                    continue;
                }

                $product = Product::query()->find($line->product_id);
                if (! $product || ! $product->track_stock) {
                    continue;
                }

                $this->stock->entry($original->company_id, $product, (float) $line->quantity, [
                    'source'  => $note,
                    'user_id' => $userId,
                    'reason'  => "Reposição por NC {$note->number}",
                ]);
            }

            // Marcar o documento original como anulado
            $original->update([
                'status'              => DocumentStatus::ANULADO->value,
                'cancelled_at'        => now(),
                'cancel_reason'       => $reason,
                'related_document_id' => $note->id,
            ]);

            return $note->fresh(['lines', 'taxes', 'signatures']);
        });
    }
}
