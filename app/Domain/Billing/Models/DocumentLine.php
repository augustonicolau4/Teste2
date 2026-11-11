<?php

namespace App\Domain\Billing\Models;

use App\Domain\Core\Enums\LineType;
use App\Domain\Core\Enums\TaxType;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLine extends BaseModel
{
    protected function casts(): array
    {
        return [
            'line_type'            => LineType::class,
            'tax_type'             => TaxType::class,
            'line_number'          => 'integer',
            'quantity'             => 'decimal:4',
            'unit_price'           => 'decimal:4',
            'discount_rate'        => 'decimal:4',
            'discount_amount'      => 'decimal:2',
            'gross_amount'         => 'decimal:2',
            'net_amount'           => 'decimal:2',
            'iva_rate'             => 'decimal:4',
            'iva_amount'           => 'decimal:2',
            'retention_applicable' => 'boolean',
            'retention_rate'       => 'decimal:4',
            'retention_amount'     => 'decimal:2',
            'total_line'           => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
