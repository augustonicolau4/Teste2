<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends BaseModel
{
    protected function casts(): array
    {
        return [
            'type'         => MovementType::class,
            'quantity'     => 'decimal:3',
            'unit_cost'    => 'decimal:2',
            'total_cost'   => 'decimal:2',
            'stock_before' => 'decimal:3',
            'stock_after'  => 'decimal:3',
            'moved_at'     => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeIncoming($query)
    {
        return $query->where('type', MovementType::ENTRADA->value);
    }

    public function scopeOutgoing($query)
    {
        return $query->where('type', MovementType::SAIDA->value);
    }
}
