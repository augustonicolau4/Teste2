<?php

namespace App\Http\Controllers;

use App\Domain\Reporting\DTOs\ReportResult;
use App\Domain\Reporting\Exports\ReportExport;
use App\Domain\Reporting\Services\ReportService;
use App\Domain\Saft\Services\SaftExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios gerenciais e fiscais.
 */
class ReportController extends Controller
{
    /**
     * Catálogo de relatórios disponíveis (chave da rota => descrição).
     *
     * @var array<string, string>
     */
    public const CATALOG = [
        'faturacao'           => 'Mapa de Faturação (por tipo de documento)',
        'faturacao-utilizador' => 'Mapa de Faturação (por utilizador)',
        'impostos'            => 'Mapa de Impostos (IVA e Retenção na Fonte)',
        'produtividade'       => 'Produtividade e Comissões',
        'stock'               => 'Movimentação e Valorização de Stock',
        'conta-corrente'      => 'Extrato de Conta-Corrente de Clientes',
        'caixa'               => 'Balancete de Caixa e Tesouraria',
    ];

    public function index(): View
    {
        return view('reports.index', ['catalog' => self::CATALOG]);
    }

    public function show(Request $request, string $report): View
    {
        return view('reports.show', [
            'report' => $this->build($request, $report),
            'key'    => $report,
        ]);
    }

    public function pdf(Request $request, string $report)
    {
        $result = $this->build($request, $report);

        return Pdf::loadView('reports.print', ['report' => $result])
            ->setPaper('a4', 'portrait')
            ->download($this->fileName($result, 'pdf'));
    }

    public function excel(Request $request, string $report)
    {
        $result = $this->build($request, $report);

        return Excel::download(new ReportExport($result), $this->fileName($result, 'xlsx'));
    }

    /**
     * Exporta o ficheiro SAF-T AO (XML) do período.
     */
    public function saft(Request $request, SaftExporter $exporter)
    {
        $companyId = (int) ($request->user()?->company_id ?? 1);
        $from = (string) $request->query('from', now()->startOfYear()->toDateString());
        $to = (string) $request->query('to', now()->toDateString());

        $xml = $exporter->xml($companyId, $from, $to);

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="SAFT-AO-' . $from . '_' . $to . '.xml"',
        ]);
    }

    private function build(Request $request, string $key): ReportResult
    {
        abort_unless(array_key_exists($key, self::CATALOG), 404);

        $companyId = (int) ($request->user()?->company_id ?? 1);
        $from = (string) $request->query('from', now()->startOfMonth()->toDateString());
        $to = (string) $request->query('to', now()->toDateString());

        $service = app(ReportService::class);

        return match ($key) {
            'faturacao'            => $service->billing($companyId, $from, $to, [
                'document_type' => $request->query('document_type'),
                'user_id'       => $request->query('user_id'),
            ]),
            'faturacao-utilizador' => $service->billingByUser($companyId, $from, $to),
            'impostos'             => $service->taxes($companyId, $from, $to),
            'produtividade'        => $service->productivity($companyId, $from, $to),
            'stock'                => $service->stock($companyId, $from, $to),
            'conta-corrente'       => $service->customerStatement(
                $companyId,
                (int) $request->query('customer_id', 1),
                $from,
                $to,
            ),
            'caixa'                => $service->cashBalance($companyId, $from, $to),
        };
    }

    private function fileName(ReportResult $result, string $extension): string
    {
        $slug = str()->slug($result->title);

        return sprintf('%s-%s.%s', $slug, now()->format('Ymd'), $extension);
    }
}
