<?php

namespace App\Domain\Reporting\Exports;

use App\Domain\Reporting\DTOs\ReportResult;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Exportação genérica de um ReportResult para Excel (.xlsx).
 */
class ReportExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly ReportResult $report)
    {
    }

    public function collection(): Collection
    {
        return collect($this->report->rows);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->report->headings;
    }

    public function title(): string
    {
        return mb_substr($this->report->title, 0, 31);
    }
}
