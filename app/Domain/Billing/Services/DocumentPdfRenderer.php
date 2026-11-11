<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Renderização dos documentos fiscais para impressão:
 *  - A4 (profissional, para empresas/frotas/seguradoras)
 *  - Ticket térmico 80mm / 58mm (POS / balcão)
 *
 * O rodapé fiscal da NICOLA DA TEC e a assinatura (4 caracteres do hash) são
 * injetados em ambos os formatos.
 */
class DocumentPdfRenderer
{
    /** Pontos por milímetro (1mm = 2.83465pt). */
    private const PT_PER_MM = 2.83465;

    public function __construct(
        private readonly QrCodeGenerator $qr,
    ) {
    }

    public function a4(Document $document, array $options = []): DomPdf
    {
        return Pdf::loadView('documents.print.a4', $this->data($document, $options))
            ->setPaper($options['paper'] ?? 'a4', 'portrait');
    }

    public function ticket(Document $document, array $options = []): DomPdf
    {
        $widthMm = (int) ($options['width_mm'] ?? config('autogest.printing.ticket.width_mm', 80));
        $heightPt = (float) ($options['height_pt'] ?? 900);

        $paper = [0, 0, $widthMm * self::PT_PER_MM, $heightPt];

        return Pdf::loadView('documents.print.ticket', $this->data($document, $options))
            ->setPaper($paper, 'portrait');
    }

    /**
     * HTML renderizado (usado pela ponte de impressão térmica Node.js).
     */
    public function html(Document $document, string $format = 'a4', array $options = []): string
    {
        $view = $format === 'ticket' ? 'documents.print.ticket' : 'documents.print.a4';

        return View::make($view, $this->data($document, $options))->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Document $document, array $options = []): array
    {
        $document->loadMissing(['lines', 'taxes', 'customer', 'vehicle', 'company', 'series', 'serviceOrder']);

        $company = $document->company;
        $producer = config('autogest.producer');
        $symbol = config('autogest.fiscal.currency_symbol', 'Kz');

        $logoPath = public_path(config('autogest.printing.a4.logo', 'images/logo.png'));
        $logoDataUri = File::exists($logoPath)
            ? 'data:image/' . File::extension($logoPath) . ';base64,' . base64_encode(File::get($logoPath))
            : null;

        $isTicket = ($options['format'] ?? 'a4') === 'ticket';
        $footer = $isTicket
            ? config('autogest.printing.ticket.footer')
            : config('autogest.printing.a4.footer');

        return [
            'document'      => $document,
            'company'       => $company,
            'producer'      => $producer,
            'currency'      => $symbol,
            'logoDataUri'   => $logoDataUri,
            'qrDataUri'     => $this->qr->dataUri($document->qrPayload()),
            'footerText'    => $footer,
            'footerHash'    => $document->footerSignature(),
            'certificate'   => $document->software_certificate ?? config('autogest.fiscal.software_certificate_number'),
            'amountInWords' => NumberToWords::money((float) $document->grand_total),
            'printDate'     => now()->format('d/m/Y H:i'),
            'isTicket'      => $isTicket,
        ];
    }
}
