<?php

namespace App\Domain\Workshop\Models;

use App\Domain\Core\Enums\LineType;
use App\Domain\Core\Enums\TaxType;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Inventory\Models\Product;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderItem extends BaseModel
{
    protected function casts(): array
    {
        return [
            'line_type'            => LineType::class,
            'tax_type'             => TaxType::class,
            'quantity'             => 'decimal:3',
            'unit_price'           => 'decimal:2',
            'cost_price'           => 'decimal:2',
            'discount_rate'        => 'decimal:4',
            'iva_rate'             => 'decimal:4',
            'retention_applicable' => 'boolean',
            'retention_rate'       => 'decimal:4',
            'total'                => 'decimal:2',
            'labor_hours'          => 'decimal:2',
            'is_approved'          => 'boolean',
            'stock_deducted'       => 'boolean',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function isLabor(): bool
    {
        return $this->line_type === LineType::SERVICE;
    }
}
