<?php

namespace App\Domain\Billing\Services;

use App\Domain\Core\Enums\DocumentType;
use App\Domain\Core\Models\Series;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Atribui a numeração sequencial por série/tipo/ano de forma transacional
 * (lock pessimista na linha da série para evitar duplicados em concorrência).
 */
class DocumentNumbering
{
    /**
     * @return array{series: Series, sequence: int, number: string}
     */
    public function next(
        DocumentType $type,
        int $companyId,
        ?int $year = null,
        ?int $branchId = null,
    ): array {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($type, $companyId, $year, $branchId) {
            $series = Series::query()
                ->where('company_id', $companyId)
                ->where('document_type', $type->value)
                ->where('year', $year)
                ->where('is_active', true)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $series) {
                $series = $this->createDefaultSeries($type, $companyId, $year, $branchId);
            }

            $sequence = (int) $series->current_sequence + 1;
            $series->current_sequence = $sequence;
            $series->last_number = $sequence;
            $series->save();

            return [
                'series'   => $series,
                'sequence' => $sequence,
                'number'   => $series->formatNumber($sequence),
            ];
        }, 3);
    }

    private function createDefaultSeries(
        DocumentType $type,
        int $companyId,
        int $year,
        ?int $branchId,
    ): Series {
        $config = config("autogest.series.{$type->value}");

        if (! $config) {
            throw new RuntimeException("Série por omissão não configurada para o tipo {$type->value}.");
        }

        return Series::query()->create([
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'document_type'    => $type->value,
            'code'             => $config['prefix'] . $year,
            'prefix'           => $config['prefix'],
            'format'           => $config['format'],
            'year'             => $year,
            'padding'          => $config['sequence_padding'] ?? 5,
            'current_sequence' => 0,
            'is_active'        => true,
        ]);
    }

    /**
     * Pré-visualiza o próximo número sem consumir a sequência.
     */
    public function peek(DocumentType $type, int $companyId, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        $series = Series::query()
            ->where('company_id', $companyId)
            ->where('document_type', $type->value)
            ->where('year', $year)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $config = config("autogest.series.{$type->value}");
        $padding = $series?->padding ?? ($config['sequence_padding'] ?? 5);
        $format = $series?->format ?? ($config['format'] ?? '{PREFIXO} {ANO}/{NUMERO}');
        $prefix = $series?->prefix ?? ($config['prefix'] ?? $type->value);
        $next = (int) ($series?->current_sequence ?? 0) + 1;

        return str_replace(
            ['{PREFIXO}', '{SERIE}', '{ANO}', '{NUMERO}'],
            [$prefix, $series?->code ?? $prefix . $year, (string) $year, str_pad((string) $next, $padding, '0', STR_PAD_LEFT)],
            $format,
        );
    }
}
