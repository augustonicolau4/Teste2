<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Models\Document;
use App\Domain\Billing\Services\DocumentPdfRenderer;
use App\Domain\Core\Enums\DocumentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API de documentos protegida por Sanctum (tokens de integração/BI).
 */
class DocumentApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'     => ['nullable', 'string'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $documents = Document::query()
            ->with(['customer:id,name', 'vehicle:id,plate'])
            ->when($data['type'] ?? null, fn ($q, $type) => $q->where('document_type', $type))
            ->when($data['from'] ?? null, fn ($q, $from) => $q->whereDate('issue_date', '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate('issue_date', '<=', $to))
            ->latest('id')
            ->paginate($data['per_page'] ?? 25);

        return response()->json($documents);
    }

    public function show(Document $document): JsonResponse
    {
        $document->loadMissing(['lines', 'taxes', 'customer', 'vehicle', 'series']);

        return response()->json([
            'data' => [
                'uuid'         => $document->uuid,
                'number'       => $document->number,
                'type'         => $document->document_type->value,
                'status'       => $document->status->value,
                'is_fiscal'    => $document->is_fiscal,
                'issue_date'   => $document->issue_date?->toDateString(),
                'customer'     => $document->customer?->only(['id', 'name', 'nif']),
                'totals'       => [
                    'net'       => (float) $document->net_total,
                    'iva'       => (float) $document->iva_total,
                    'retention' => (float) $document->retention_total,
                    'grand'     => (float) $document->grand_total,
                    'paid'      => (float) $document->paid_total,
                    'balance'   => $document->remainingBalance(),
                ],
                'footer_signature' => $document->footerSignature(),
                'hash'             => $document->hash,
                'qr_payload'       => $document->qr_payload,
            ],
        ]);
    }

    public function pdf(Document $document, string $format, DocumentPdfRenderer $renderer): Response
    {
        abort_unless(in_array($format, ['a4', 'ticket'], true), 404);

        $pdf = $format === 'ticket'
            ? $renderer->ticket($document, ['format' => 'ticket'])
            : $renderer->a4($document);

        return $pdf->download($this->fileName($document, $format));
    }

    private function fileName(Document $document, string $format): string
    {
        $safe = str_replace(['/', ' '], '-', $document->number);

        return sprintf('%s-%s.pdf', $document->document_type->value . '-' . $safe, $format);
    }
}
