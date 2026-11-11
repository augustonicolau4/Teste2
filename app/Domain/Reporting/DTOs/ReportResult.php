<?php

namespace App\Domain\Reporting\DTOs;

/**
 * Resultado genérico de um relatório, pronto para renderização em HTML,
 * PDF (dompdf) ou Excel (maatwebsite/excel).
 */
final readonly class ReportResult
{
    /**
     * @param  list<string>  $headings  Cabeçalhos das colunas.
     * @param  list<array<int, string|float|int|null>>  $rows  Linhas (mesma ordem das colunas).
     * @param  array<string, string|float|int|null>  $totals  Totais (rótulo => valor).
     * @param  array<string, mixed>  $meta  Metadados (filtros aplicados, subtítulo, etc.).
     */
    public function __construct(
        public string $title,
        public array $headings,
        public array $rows,
        public array $totals = [],
        public array $meta = [],
    ) {
    }
}
