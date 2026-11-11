<?php

namespace App\Domain\Saft\Services;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Enums\DocumentStatus;
use App\Domain\Core\Enums\DocumentType;
use App\Domain\Core\Models\Company;
use App\Domain\CRM\Models\Customer;
use App\Domain\Inventory\Models\Product;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Exportação do ficheiro SAF-T AO (Standard Audit File for Tax purposes).
 *
 * Estrutura conforme o modelo da AGT (AuditFile / Header / MasterFiles / SourceDocuments).
 */
class SaftExporter
{
    private const NS = 'urn:OECD:StandardAuditFile-Tax:AO_1.01_01';

    public function __construct()
    {
    }

    public function xml(int $companyId, string $from, string $to, ?int $branchId = null): string
    {
        $company = Company::query()->findOrFail($companyId);

        $documents = Document::query()
            ->where('company_id', $companyId)
            ->where('is_fiscal', true)
            ->whereBetween('issue_date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['customer', 'lines', 'taxes'])
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS, 'AuditFile');
        $dom->appendChild($root);

        $this->header($dom, $root, $company, $from, $to);
        $this->masterFiles($dom, $root, $company);

        $sourceDocuments = $dom->createElement('SourceDocuments');
        $root->appendChild($sourceDocuments);
        $this->salesInvoices($dom, $sourceDocuments, $documents);

        return $dom->saveXML() ?: throw new RuntimeException('Falha ao gerar o XML do SAF-T.');
    }

    /**
     * Gera e guarda o ficheiro, devolvendo o caminho relativo em storage.
     */
    public function store(int $companyId, string $from, string $to, ?int $branchId = null): string
    {
        $xml = $this->xml($companyId, $from, $to, $branchId);
        $path = sprintf('%s/saft_ao_%d_%s_%s.xml', config('autogest.saft.export_path', 'saft'), $companyId, $from, $to);

        Storage::disk('local')->put($path, $xml);

        Document::query()
            ->where('company_id', $companyId)
            ->whereBetween('issue_date', [$from, $to])
            ->update(['saft_exported_at' => now()]);

        return $path;
    }

    private function header(DOMDocument $dom, DOMElement $root, Company $company, string $from, string $to): void
    {
        $header = $dom->createElement('Header');
        $root->appendChild($header);

        $this->append($dom, $header, 'AuditFileVersion', config('autogest.saft.version', '1.01_01'));
        $this->append($dom, $header, 'CompanyID', (string) $company->nif);
        $this->append($dom, $header, 'TaxRegistrationNumber', (string) $company->nif);
        $this->append($dom, $header, 'TaxAccountingBasis', 'F');
        $this->append($dom, $header, 'CompanyName', $company->name);
        $this->append($dom, $header, 'BusinessName', $company->trade_name ?? $company->name);

        $address = $dom->createElement('CompanyAddress');
        $header->appendChild($address);
        $this->append($dom, $address, 'AddressDetail', $company->address ?? 'N/D');
        $this->append($dom, $address, 'City', $company->city ?? 'Luanda');
        $this->append($dom, $address, 'Country', $company->country ?? 'AO');

        $this->append($dom, $header, 'FiscalYear', substr($from, 0, 4));
        $this->append($dom, $header, 'StartDate', $from);
        $this->append($dom, $header, 'EndDate', $to);
        $this->append($dom, $header, 'CurrencyCode', config('autogest.fiscal.currency', 'AOA'));
        $this->append($dom, $header, 'DateCreated', now()->format('Y-m-d'));
        $this->append($dom, $header, 'TaxEntity', 'Global');
        $this->append($dom, $header, 'ProductID', config('autogest.saft.product_id', 'autogest_nicola/1.0.0'));
        $this->append($dom, $header, 'ProductVersion', config('autogest.saft.product_version', '1.0.0'));
        $this->append($dom, $header, 'SoftwareValidationNumber', config('autogest.fiscal.software_certificate_number', ''));
        $this->append($dom, $header, 'ProductCompanyTaxID', config('autogest.producer.nif', '5002537540'));
        $this->append($dom, $header, 'SoftwareCertificateNumber', config('autogest.fiscal.software_certificate_number', ''));
    }

    private function masterFiles(DOMDocument $dom, DOMElement $root, Company $company): void
    {
        $master = $dom->createElement('MasterFiles');
        $root->appendChild($master);

        // Clientes
        Customer::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->chunk(200, function ($customers) use ($dom, $master) {
                foreach ($customers as $customer) {
                    $el = $dom->createElement('Customer');
                    $master->appendChild($el);
                    $this->append($dom, $el, 'CustomerID', (string) $customer->id);
                    $this->append($dom, $el, 'AccountID', 'Desconhecido');
                    $this->append($dom, $el, 'CustomerTaxID', $customer->nif ?? '999999999');
                    $this->append($dom, $el, 'CompanyName', $customer->name);

                    $addr = $dom->createElement('BillingAddress');
                    $el->appendChild($addr);
                    $this->append($dom, $addr, 'AddressDetail', $customer->address ?? 'N/D');
                    $this->append($dom, $addr, 'City', $customer->city ?? 'Luanda');
                    $this->append($dom, $addr, 'Country', $customer->country ?? 'AO');

                    $this->append($dom, $el, 'SelfBillingIndicator', '0');
                }
            });

        // Produtos
        Product::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->chunk(200, function ($products) use ($dom, $master) {
                foreach ($products as $product) {
                    $el = $dom->createElement('Product');
                    $master->appendChild($el);
                    $this->append($dom, $el, 'ProductType', $product->type === 'service' ? 'S' : 'P');
                    $this->append($dom, $el, 'ProductCode', $product->sku ?? (string) $product->id);
                    $this->append($dom, $el, 'ProductDescription', $product->name);
                    $this->append($dom, $el, 'ProductNumberCode', $product->oem_code ?? $product->sku ?? (string) $product->id);
                }
            });

        // Tabela de impostos
        $taxTable = $dom->createElement('TaxTable');
        $master->appendChild($taxTable);

        foreach ($this->taxEntries() as $entry) {
            $el = $dom->createElement('TaxTableEntry');
            $taxTable->appendChild($el);
            $this->append($dom, $el, 'TaxType', $entry['type']);
            $this->append($dom, $el, 'TaxCountryRegion', config('autogest.saft.tax_country', 'AO'));
            $this->append($dom, $el, 'TaxCode', $entry['code']);
            $this->append($dom, $el, 'Description', $entry['description']);
            $this->append($dom, $el, 'TaxPercentage', number_format($entry['rate'] * 100, 2, '.', ''));
        }
    }

    private function salesInvoices(DOMDocument $dom, DOMElement $parent, $documents): void
    {
        $invoices = $dom->createElement('SalesInvoices');
        $parent->appendChild($invoices);

        $creditDocs = $documents->filter(fn (Document $d) => in_array($d->document_type, [DocumentType::NOTA_CREDITO, DocumentType::NOTA_DEBITO], true));
        $totalCredit = $documents->sum(fn (Document $d) => (float) $d->grand_total);
        $totalDebit = 0.0;

        $this->append($dom, $invoices, 'NumberOfEntries', (string) $documents->count());
        $this->append($dom, $invoices, 'TotalDebit', number_format($totalDebit, 2, '.', ''));
        $this->append($dom, $invoices, 'TotalCredit', number_format($totalCredit, 2, '.', ''));

        foreach ($documents as $document) {
            $this->invoice($dom, $invoices, $document);
        }
    }

    private function invoice(DOMDocument $dom, DOMElement $parent, Document $document): void
    {
        $el = $dom->createElement('Invoice');
        $parent->appendChild($el);

        $this->append($dom, $el, 'InvoiceNo', $document->number);

        $status = $dom->createElement('DocumentStatus');
        $el->appendChild($status);
        $this->append($dom, $status, 'InvoiceStatus', $document->isCancelled() ? 'A' : 'N');
        $this->append($dom, $status, 'InvoiceStatusDate', ($document->cancelled_at ?? $document->system_entry_date)?->format('Y-m-d\TH:i:s'));
        $this->append($dom, $status, 'SourceID', (string) $document->user_id);
        $this->append($dom, $status, 'SourceBilling', 'P');

        $this->append($dom, $el, 'Hash', (string) $document->hash);
        $this->append($dom, $el, 'HashControl', (string) ($document->hash_control ? '1' : '1'));
        $this->append($dom, $el, 'InvoiceDate', $document->issue_date?->format('Y-m-d'));
        $this->append($dom, $el, 'SystemEntryDate', $document->system_entry_date?->format('Y-m-d\TH:i:s'));
        $this->append($dom, $el, 'CustomerID', (string) $document->customer_id);
        $this->append($dom, $el, 'InvoiceType', $document->document_type->safTCode());

        if ($document->document_type === DocumentType::NOTA_CREDITO && $document->related_document_id) {
            $ref = $dom->createElement('SpecialRegimes');
            $el->appendChild($ref);
        }

        foreach ($document->lines as $line) {
            $lineEl = $dom->createElement('Line');
            $el->appendChild($lineEl);
            $this->append($dom, $lineEl, 'LineNumber', (string) $line->line_number);
            $this->append($dom, $lineEl, 'ProductCode', (string) ($line->product_id ?? 'SERVICOS'));
            $this->append($dom, $lineEl, 'ProductDescription', $line->description);
            $this->append($dom, $lineEl, 'Quantity', number_format((float) $line->quantity, 3, '.', ''));
            $this->append($dom, $lineEl, 'UnitOfMeasure', $line->unit_code ?? 'UN');
            $this->append($dom, $lineEl, 'UnitPrice', number_format((float) $line->unit_price, 4, '.', ''));
            $this->append($dom, $lineEl, 'CreditAmount', number_format((float) $line->net_amount, 2, '.', ''));

            if ((float) $line->discount_amount > 0) {
                $disc = $dom->createElement('Discount');
                $lineEl->appendChild($disc);
                $this->append($dom, $disc, 'DiscountAmount', number_format((float) $line->discount_amount, 2, '.', ''));
            }

            if ($line->exemption_code) {
                $this->append($dom, $lineEl, 'TaxExemptionReason', $line->exemption_code);
            }

            $tax = $dom->createElement('Tax');
            $lineEl->appendChild($tax);
            $this->append($dom, $tax, 'TaxType', $line->tax_type->value);
            $this->append($dom, $tax, 'TaxCountryRegion', config('autogest.saft.tax_country', 'AO'));
            $this->append($dom, $tax, 'TaxCode', $line->tax_type->value);
            $this->append($dom, $tax, 'TaxPercentage', number_format((float) $line->iva_rate * 100, 2, '.', ''));
        }

        $totals = $dom->createElement('DocumentTotals');
        $el->appendChild($totals);
        $this->append($dom, $totals, 'TaxPayable', number_format((float) $document->iva_total, 2, '.', ''));
        $this->append($dom, $totals, 'NetTotal', number_format((float) $document->net_total, 2, '.', ''));
        $this->append($dom, $totals, 'GrossTotal', number_format((float) $document->grand_total, 2, '.', ''));
    }

    /**
     * @return array<int, array{type:string, code:string, description:string, rate:float}>
     */
    private function taxEntries(): array
    {
        return [
            ['type' => 'IVA', 'code' => 'NOR', 'description' => 'IVA Normal', 'rate' => (float) config('autogest.fiscal.iva_rate', 0.14)],
            ['type' => 'IVA', 'code' => 'RED', 'description' => 'IVA Reduzida', 'rate' => (float) config('autogest.fiscal.iva_reduced_rate', 0.07)],
            ['type' => 'IVA', 'code' => 'ISE', 'description' => 'Isento de IVA', 'rate' => 0.0],
            ['type' => 'IS', 'code' => 'RET', 'description' => 'Retenção na Fonte', 'rate' => (float) config('autogest.fiscal.retention_rate', 0.065)],
        ];
    }

    private function append(DOMDocument $dom, DOMElement $parent, string $name, ?string $value): DOMElement
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode((string) $value));
        $parent->appendChild($element);

        return $element;
    }
}
