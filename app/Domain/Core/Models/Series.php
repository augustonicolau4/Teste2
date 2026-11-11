<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Enums\DocumentType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Series extends BaseModel
{
    protected function casts(): array
    {
        return [
            'document_type'    => DocumentType::class,
            'year'             => 'integer',
            'current_sequence' => 'integer',
            'last_number'      => 'integer',
            'padding'          => 'integer',
            'is_active'        => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForType($query, DocumentType|string $type)
    {
        $value = $type instanceof DocumentType ? $type->value : $type;

        return $query->where('document_type', $value);
    }

    /**
     * Formata o próximo número da série (ex.: "FT 2026/00001").
     */
    public function formatNumber(int $sequence): string
    {
        return str_replace(
            ['{PREFIXO}', '{SERIE}', '{ANO}', '{NUMERO}'],
            [$this->prefix, $this->code, (string) $this->year, str_pad((string) $sequence, $this->padding, '0', STR_PAD_LEFT)],
            $this->format,
        );
    }
}
