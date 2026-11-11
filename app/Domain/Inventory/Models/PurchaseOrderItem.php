<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends BaseModel
{
    protected function casts(): array
    {
        return [
            'quantity'          => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_cost'         => 'decimal:2',
            'iva_rate'          => 'decimal:4',
            'total'             => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pendingQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_quantity);
    }
}
