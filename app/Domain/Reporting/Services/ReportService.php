<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Enums\DocumentType;
use App\Domain\Finance\Models\LedgerEntry;
use App\Domain\Finance\Models\Payment;
use App\Domain\HR\Models\Commission;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Reporting\DTOs\ReportResult;
use Illuminate\Support\Collection;

/**
 * Agregação de dados para os relatórios gerenciais e fiscais do autogest_nicola.
 *
 * Todos os métodos devolvem um ReportResult (cabeçalhos + linhas + totais) que
 * é renderizado em HTML, PDF ou Excel pelo ReportController.
 */
class ReportService
{
    public function billing(int $companyId, string $from, string $to, array $filters = []): ReportResult
    {
        $documents = $this->documents($companyId, $from, $to, $filters);

        $rows = $documents
            ->groupBy(fn (Document $d) => $d->document_type->value)
            ->map(fn (Collection $items, string $type) => [
                DocumentType::from($type)->label(),
                $items->count(),
                round($items->sum('net_total'), 2),
                round($items->sum('iva_total'), 2),
                round($items->sum('retention_total'), 2),
                round($items->sum('grand_total'), 2),
            ])
            ->values()
            ->all();

        return new ReportResult(
            title: 'Mapa de Faturação',
            headings: ['Tipo de Documento', 'Nº Docs', 'Base Tributável', 'IVA', 'Retenção', 'Total'],
            rows: $rows,
            totals: [
                'Nº Documentos'   => $documents->count(),
                'Base Tributável' => round($documents->sum('net_total'), 2),
                'IVA'             => round($documents->sum('iva_total'), 2),
                'Retenção'        => round($documents->sum('retention_total'), 2),
                'Total Geral'     => round($documents->sum('grand_total'), 2),
            ],
            meta: ['from' => $from, 'to' => $to],
        );
    }

    public function billingByUser(int $companyId, string $from, string $to): ReportResult
    {
        $documents = $this->documents($companyId, $from, $to)->loadMissing('user');

        $rows = $documents
            ->groupBy(fn (Document $d) => $d->user_id ?? 0)
            ->map(fn (Collection $items) => [
                $items->first()->user?->name ?? 'Sistema',
                $items->count(),
                round($items->sum('net_total'), 2),
                round($items->sum('iva_total'), 2),
                round($items->sum('grand_total'), 2),
            ])
            ->values()
            ->all();

        return new ReportResult(
            title: 'Mapa de Faturação por Utilizador',
            headings: ['Utilizador', 'Nº Docs', 'Base Tributável', 'IVA', 'Total'],
            rows: $rows,
            totals: [
                'Base Tributável' => round($documents->sum('net_total'), 2),
                'IVA'             => round($documents->sum('iva_total'), 2),
                'Total Geral'     => round($documents->sum('grand_total'), 2),
            ],
            meta: ['from' => $from, 'to' => $to],
        );
    }

    /**
     * Mapa de impostos: IVA liquidado e retenção na fonte, agrupados por imposto/taxa.
     */
    public function taxes(int $companyId, string $from, string $to): ReportResult
    {
        $documents = $this->documents($companyId, $from, $to)->loadMissing('taxes');

        $grouped = collect();
        foreach ($documents as $document) {
            foreach ($document->taxes as $tax) {
                $key = $tax->tax_type . '|' . (float) $tax->rate;
                $entry = $grouped->get($key, [
                    'description' => $tax->description ?? $tax->tax_type,
                    'rate'        => (float) $tax->rate,
                    'base'        => 0.0,
                    'amount'      => 0.0,
                ]);
                $entry['base'] += (float) $tax->base_amount;
                $entry['amount'] += (float) $tax->amount;
                $grouped->put($key, $entry);
            }
        }

        $rows = $grouped->map(fn (array $e) => [
            $e['description'],
            round($e['rate'] * 100, 2),
            round($e['base'], 2),
            round($e['amount'], 2),
        ])->values()->all();

        return new ReportResult(
            title: 'Mapa de Impostos (IVA e Retenção na Fonte)',
            headings: ['Imposto', 'Taxa (%)', 'Base Tributável', 'Valor Apurado'],
            rows: $rows,
            totals: [
                'IVA Liquidado'      => round($documents->sum('iva_total'), 2),
                'Retenção na Fonte'  => round($documents->sum('retention_total'), 2),
            ],
            meta: ['from' => $from, 'to' => $to],
        );
    }

    public function productivity(int $companyId, string $from, string $to): ReportResult
    {
        $commissions = Commission::query()
            ->where('company_id', $companyId)
            ->whereBetween('reference_period', [substr($from, 0, 7), substr($to, 0, 7)])
            ->with(['employee:id,name', 'user:id,name'])
            ->get();

        $rows = $commissions
            ->groupBy(fn (Commission $c) => $c->role . '|' . ($c->employee_id ?? $c->user_id ?? 0))
            ->map(function (Collection $items) {
                $first = $items->first();
                $name = $first->employee?->name ?? $first->user?->name ?? 'N/D';

                return [
                    $first->role === 'technician' ? 'Mecânico' : 'Promotor',
                    $name,
                    $items->count(),
                    round($items->sum('base_amount'), 2),
                    round($items->sum('amount'), 2),
                    round($items->where('status', 'paid')->sum('amount'), 2),
                ];
            })
            ->values()
            ->all();

        return new ReportResult(
            title: 'Relatório de Produtividade e Comissões',
            headings: ['Função', 'Colaborador', 'Nº Operações', 'Base', 'Comissão', 'Já Pago'],
            rows: $rows,
            totals: [
                'Base Total'      => round($commissions->sum('base_amount'), 2),
                'Comissões'       => round($commissions->sum('amount'), 2),
                'Comissões Pagas' => round($commissions->where('status', 'paid')->sum('amount'), 2),
            ],
            meta: ['from' => $from, 'to' => $to],
        );
    }

    /**
     * Movimentação e valorização de stock (entradas/saídas por peça).
     */
    public function stock(int $companyId, string $from, string $to): ReportResult
    {
        $movements = StockMovement::query()
            ->where('company_id', $companyId)
            ->whereBetween('moved_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->with('product:id,name,sku,cost_price,sale_price,reorder_level,track_stock,type')
            ->get();

        $rows = $movements
            ->groupBy('product_id')
            ->map(function (Collection $items) {
                $product = $items->first()->product;
                $in = $items->where('type', \App\Domain\Inventory\Enums\MovementType::ENTRADA->value)->sum('quantity');
                $out = $items->where('type', \App\Domain\Inventory\Enums\MovementType::SAIDA->value)->sum('quantity');

                return [
                    $product?->name ?? 'N/D',
                    $product?->sku ?? '-',
                    round((float) $in, 3),
                    round((float) $out, 3),
                    round($product ? $product->stockOnHand() : 0, 3),
                    round((float) ($product?->cost_price ?? 0), 2),
                    round((float) ($product?->cost_price ?? 0) * ($product?->stockOnHand() ?? 0), 2),
                ];
            })
            ->sortByDesc(fn ($r) => $r[3])
            ->values()
            ->all();

        $lowStock = Product::query()
            ->where('company_id', $companyId)
            ->where('track_stock', true)
            ->get()
            ->filter(fn (Product $p) => $p->isLowStock());

        return new ReportResult(
            title: 'Movimentação e Valorização de Stock',
            headings: ['Peça/Produto', 'SKU', 'Entradas', 'Saídas', 'Stock Atual', 'Custo Unit.', 'Valor Stock'],
            rows: $rows,
            totals: [
                'Artigos Movimentados' => $movements->pluck('product_id')->unique()->count(),
                'Alertas de Rutura'    => $lowStock->count(),
                'Valor Total em Stock' => round($rows ? collect($rows)->sum(fn ($r) => $r[6]) : 0, 2),
            ],
            meta: ['from' => $from, 'to' => $to],
        );
    }

    public function customerStatement(int $companyId, int $customerId, string $from, string $to): ReportResult
    {
        $entries = LedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('account_type', 'customer')
            ->where('account_id', $customerId)
            ->whereBetween('entry_date', [$from, $to])
            ->orderBy('id')
            ->get();

        $opening = (float) LedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('account_type', 'customer')
            ->where('account_id', $customerId)
            ->where('entry_date', '<', $from)
            ->orderByDesc('id')
            ->value('balance_after');

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                $entry->entry_date?->format('d/m/Y'),
                $entry->description,
                $entry->entry_type->value === 'debit' ? round((float) $entry->amount, 2) : null,
                $entry->entry_type->value === 'credit' ? round((float) $entry->amount, 2) : null,
                round((float) $entry->balance_after, 2),
            ];
        }

        return new ReportResult(
            title: 'Extrato de Conta-Corrente do Cliente',
            headings: ['Data', 'Descrição', 'Débito', 'Crédito', 'Saldo'],
            rows: $rows,
            totals: [
                'Saldo Inicial' => round($opening, 2),
                'Saldo Final'   => round((float) ($entries->last()->balance_after ?? $opening), 2),
            ],
            meta: ['from' => $from, 'to' => $to, 'customer_id' => $customerId],
        );
    }

    public function cashBalance(int $companyId, string $from, string $to): ReportResult
    {
        $payments = Payment::query()
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get();

        $rows = $payments
            ->groupBy('method')
            ->map(function (Collection $items, string $method) {
                $label = \App\Domain\Core\Enums\PaymentMethod::tryFrom($method)?->label() ?? $method;

                return [
                    $label,
                    $items->count(),
                    round($items->where('direction', 'in')->sum('amount'), 2),
                    round($items->where('direction', 'out')->sum('amount'), 2),
                ];
            })
            ->values()
            ->all();

        return new ReportResult(
            title: 'Balancete de Caixa e Tesouraria',
            headings: ['Meio de Pagamento', 'Nº Operações', 'Entradas', 'Saídas'],
            rows: $rows,
            totals: [
                'Total Entradas' => round($payments->where('direction', 'in')->sum('amount'), 2),
                'Total Saídas'   => round($payments->where('direction', 'out')->sum('amount'), 2),
                'Saldo do Período' => round(
                    $payments->where('direction', 'in')->sum('amount')
                    - $payments->where('direction', 'out')->sum('amount'),
                    2
                ),
            ],
            meta: ['from' => $from, 'to' => $to],
        );
    }

    /**
     * @param  array{document_type?:string|null,user_id?:int|null}  $filters
     * @return Collection<int, Document>
     */
    private function documents(int $companyId, string $from, string $to, array $filters = []): Collection
    {
        return Document::query()
            ->where('company_id', $companyId)
            ->where('is_fiscal', true)
            ->whereBetween('issue_date', [$from, $to])
            ->when($filters['document_type'] ?? null, fn ($q, $t) => $q->where('document_type', $t))
            ->when($filters['user_id'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->get();
    }
}
