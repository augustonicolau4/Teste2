<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Enums\DocumentStatus;

/**
 * Cadeia de hash dos documentos fiscais (AGT).
 *
 * Hash = Base64( SHA1( DataEmissão ; DataRegisto ; NºDocumento ;
 *                       TotalBruto ; HashControl ; HashAnterior ) )
 *
 * O documento seguinte encadeia o hash do anterior, garantindo inalterabilidade.
 */
class HashChain
{
    /**
     * Calcula o hash do documento.
     */
    public function compute(
        string $issueDate,
        string $systemEntryDate,
        string $number,
        float $grandTotal,
        string $hashControl,
        ?string $previousHash,
    ): string {
        $payload = implode(';', [
            $issueDate,
            $systemEntryDate,
            $number,
            number_format($grandTotal, 2, '.', ''),
            $hashControl,
            (string) $previousHash,
        ]);

        return base64_encode(sha1($payload, true));
    }

    /**
     * Hash do último documento fiscal assinado (para encadeamento).
     */
    public function lastHash(int $companyId, ?string $excludeUuid = null): ?string
    {
        return Document::query()
            ->where('company_id', $companyId)
            ->where('is_fiscal', true)
            ->whereNotNull('hash')
            ->whereNotIn('status', [DocumentStatus::RASCUNHO->value])
            ->when($excludeUuid, fn ($q) => $q->where('uuid', '!=', $excludeUuid))
            ->orderByDesc('id')
            ->value('hash');
    }

    /**
     * Verifica a integridade de uma sequência encadeada de documentos.
     *
     * @param  iterable<Document>  $documents  (ordenados por id crescente)
     * @return array{valid: bool, broken_at: ?int}
     */
    public function verifySequence(iterable $documents): array
    {
        $previous = null;

        foreach ($documents as $document) {
            $expected = $this->compute(
                $document->issue_date?->format('Y-m-d') ?? '',
                $document->system_entry_date?->format('Y-m-d\TH:i:s') ?? '',
                $document->number,
                (float) $document->grand_total,
                (string) ($document->signature_algorithm ? '1' : '1'),
                $previous,
            );

            if ($document->hash !== $expected) {
                return ['valid' => false, 'broken_at' => $document->id];
            }

            $previous = $document->hash;
        }

        return ['valid' => true, 'broken_at' => null];
    }
}
