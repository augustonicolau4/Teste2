<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Models\Document;
use App\Domain\Billing\Services\DocumentPdfRenderer;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verificação pública de documentos (destino do QR Code) e descarga do PDF.
 * O UUID não sequencial funciona como segredo de acesso.
 */
class PublicDocumentController extends Controller
{
    public function verify(string $uuid): View
    {
        $document = Document::query()
            ->with(['company', 'customer:id,name', 'lines', 'taxes'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return view('documents.public', ['document' => $document]);
    }

    public function pdf(string $uuid, DocumentPdfRenderer $renderer): Response
    {
        $document = Document::query()->where('uuid', $uuid)->firstOrFail();

        return $renderer->a4($document)->stream('documento-' . $document->uuid . '.pdf');
    }
}
