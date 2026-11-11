<?php

namespace App\Domain\CRM\Models;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\Workshop\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'year'              => 'integer',
            'current_km'        => 'integer',
            'next_service_km'   => 'integer',
            'last_service_at'   => 'date',
            'insurance_expiry'  => 'date',
            'inspection_expiry' => 'date',
            'is_active'         => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return trim(sprintf('%s %s %s', $this->brand, $this->model, $this->plate));
    }

    public function scopeSearch($query, ?string $term)
    {
        return $query->when($term, fn ($q) => $q->where(function ($q) use ($term) {
            $q->where('plate', 'like', "%{$term}%")
              ->orWhere('vin', 'like', "%{$term}%")
              ->orWhere('brand', 'like', "%{$term}%")
              ->orWhere('model', 'like', "%{$term}%");
        }));
    }
}
