<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'order_date'    => 'date',
            'expected_date' => 'date',
            'received_at'   => 'datetime',
            'subtotal'      => 'decimal:2',
            'iva_total'     => 'decimal:2',
            'total'         => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function isFullyReceived(): bool
    {
        return $this->items->every(fn (PurchaseOrderItem $i) => $i->received_quantity >= $i->quantity);
    }
}
