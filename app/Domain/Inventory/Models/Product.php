<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Core\Concerns\Auditable;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\Core\Models\Tax;
use App\Domain\Core\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel
{
    use Auditable;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'cost_price'            => 'decimal:2',
            'sale_price'            => 'decimal:2',
            'iva_rate'              => 'decimal:4',
            'stock_min'             => 'decimal:3',
            'stock_max'             => 'decimal:3',
            'reorder_level'         => 'decimal:3',
            'retention_applicable'  => 'boolean',
            'track_stock'           => 'boolean',
            'is_active'             => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Stock atual (soma assinada dos movimentos).
     */
    public function stockOnHand(?int $warehouseId = null): float
    {
        return (float) $this->movements()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stockOnHand() <= (float) $this->reorder_level;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeParts(Builder $query): Builder
    {
        return $query->whereIn('type', ['part', 'product', 'consumable']);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn ($q) => $q->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%")
              ->orWhere('oem_code', 'like', "%{$term}%")
              ->orWhere('barcode', 'like', "%{$term}%");
        }));
    }

    public function marginPercent(): float
    {
        $cost = (float) $this->cost_price;

        return $cost > 0 ? round((((float) $this->sale_price - $cost) / $cost) * 100, 2) : 0.0;
    }
}
