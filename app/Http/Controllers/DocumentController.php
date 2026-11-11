<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Actions\CancelDocumentAction;
use App\Domain\Billing\Actions\IssueDocumentAction;
use App\Domain\Billing\DTOs\DocumentData;
use App\Domain\Billing\Models\Document;
use App\Domain\Billing\Services\DocumentPdfRenderer;
use App\Domain\Core\Enums\DocumentType;
use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\Vehicle;
use App\Domain\Inventory\Models\Product;
use App\Domain\Notification\Services\WhatsAppGateway;
use App\Http\Requests\StoreDocumentRequest;
use App\Domain\Workshop\Models\ServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 1);

        $documents = Document::query()
            ->with(['customer:id,name', 'vehicle:id,plate'])
            ->where('company_id', $companyId)
            ->when($request->query('type'), fn ($q, $t) => $q->where('document_type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('issue_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('issue_date', '<=', $d))
            ->when($request->query('q'), fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('number', 'like', "%{$term}%")->orWhere('hash', 'like', "%{$term}%");
            }))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'types'     => DocumentType::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 1);

        return view('documents.create', [
            'customers'   => Customer::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'nif']),
            'vehicles'    => Vehicle::query()->where('company_id', $companyId)->get(['id', 'plate', 'brand', 'model', 'customer_id']),
            'products'    => Product::query()->where('company_id', $companyId)->active()->get(['id', 'name', 'sku', 'sale_price', 'type', 'iva_rate']),
            'orders'      => ServiceOrder::query()->where('company_id', $companyId)->open()->get(['id', 'number', 'customer_id', 'vehicle_id']),
            'types'       => DocumentType::options(),
        ]);
    }

    public function store(StoreDocumentRequest $request, IssueDocumentAction $action): RedirectResponse
    {
        $user = $request->user();

        $lines = collect($request->validated('lines'))->map(fn (array $line) => [
            'description'    => $line['description'],
            'quantity'       => (float) $line['quantity'],
            'unit_price'     => (float) $line['unit_price'],
            'line_type'      => $line['line_type'] ?? 'service',
            'product_id'     => $line['product_id'] ?? null,
            'unit_code'      => $line['unit_code'] ?? null,
            // Percentagens (0-100) -> frações (0-1)
            'discount_rate'  => ((float) ($line['discount_rate'] ?? 0)) / 100,
            'retention_rate' => isset($line['retention_rate'])
                ? ((float) $line['retention_rate']) / 100
                : null,
            'exemption_code' => $line['exemption_code'] ?? null,
        ])->all();

        $document = $action->execute(new DocumentData(
            companyId: (int) $user->company_id,
            documentType: DocumentType::from($request->validated('document_type')),
            lines: $lines,
            customerId: (int) $request->validated('customer_id'),
            vehicleId: $request->validated('vehicle_id') ? (int) $request->validated('vehicle_id') : null,
            branchId: $user->branch_id,
            serviceOrderId: $request->validated('service_order_id') ? (int) $request->validated('service_order_id') : null,
            userId: $user->id,
            issueDate: $request->validated('issue_date'),
            dueDate: $request->validated('due_date'),
            notes: $request->validated('notes'),
            deductStock: (bool) ($request->validated('deduct_stock') ?? true),
            payments: collect($request->validated('payments') ?? [])
                ->map(fn (array $p) => [
                    'method'    => $p['method'],
                    'amount'    => (float) $p['amount'],
                    'reference' => $p['reference'] ?? null,
                ])
                ->all(),
        ));

        return redirect()
            ->route('documents.show', $document)
            ->with('status', "Documento {$document->number} emitido com sucesso.");
    }

    public function show(Document $document): View
    {
        $document->loadMissing(['lines', 'taxes', 'signatures', 'customer', 'vehicle', 'series', 'serviceOrder', 'user', 'payments']);

        return view('documents.show', ['document' => $document]);
    }

    /**
     * Descarrega o PDF no formato A4 ou Ticket (térmico).
     */
    public function pdf(Document $document, string $format, DocumentPdfRenderer $renderer): Response
    {
        abort_unless(in_array($format, ['a4', 'ticket'], true), 404);

        $pdf = $format === 'ticket'
            ? $renderer->ticket($document, ['format' => 'ticket'])
            : $renderer->a4($document);

        $document->increment('printed_count');

        return $pdf->download($this->pdfName($document, $format));
    }

    /**
     * Envia o documento para a ponte de impressão térmica (Node.js).
     */
    public function print(Document $document, string $format, WhatsAppGateway $gateway, DocumentPdfRenderer $renderer): RedirectResponse
    {
        abort_unless(in_array($format, ['a4', 'ticket'], true), 404);

        $pdf = $format === 'ticket'
            ? $renderer->ticket($document, ['format' => 'ticket'])
            : $renderer->a4($document);

        $job = $gateway->print($format, base64_encode($pdf->output()), $this->pdfName($document, $format), [
            'company_id' => $document->company_id,
            'related'    => $document,
        ]);

        $document->increment('printed_count');

        return back()->with(
            $job->status === 'failed' ? 'error' : 'status',
            $job->status === 'failed'
                ? 'Falha ao enviar para a impressora: ' . $job->error
                : 'Documento enviado para impressão (' . strtoupper($format) . ').'
        );
    }

    /**
     * Envia o PDF do documento por WhatsApp ao cliente.
     */
    public function sendWhatsApp(Document $document, WhatsAppGateway $gateway, DocumentPdfRenderer $renderer): RedirectResponse
    {
        $phone = $document->customer?->phone;

        if (! $phone) {
            return back()->with('error', 'O cliente não tem número de telefone associado.');
        }

        $pdf = $renderer->a4($document);

        $message = $gateway->sendDocument(
            $phone,
            base64_encode($pdf->output()),
            null,
            $this->pdfName($document, 'a4'),
            sprintf('Segue o documento %s (%s). Obrigado.', $document->number, $document->document_type->label()),
            ['company_id' => $document->company_id, 'related' => $document],
        );

        return back()->with(
            $message->status->value === 'failed' ? 'error' : 'status',
            $message->status->value === 'failed'
                ? 'Falha no envio por WhatsApp: ' . $message->error
                : 'Documento enviado por WhatsApp.'
        );
    }

    /**
     * Anula o documento através da emissão de uma Nota de Crédito.
     */
    public function cancel(Request $request, Document $document, CancelDocumentAction $action): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $note = $action->execute($document, $request->input('reason'), $request->user()?->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('documents.show', $note)
            ->with('status', "Nota de Crédito {$note->number} emitida (anulação de {$document->number}).");
    }

    private function pdfName(Document $document, string $format): string
    {
        $safe = str_replace(['/', ' '], '-', $document->number);

        return sprintf('%s-%s-%s.pdf', $document->document_type->value, $safe, $format);
    }
}
