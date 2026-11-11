<?php

namespace App\Domain\CRM\Models;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Concerns\Auditable;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\Finance\Models\LedgerEntry;
use App\Domain\Finance\Models\Payment;
use App\Domain\User\Models\User;
use App\Domain\Workshop\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends BaseModel
{
    use Auditable;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'balance'      => 'decimal:2',
            'is_active'    => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoter_id');
    }

    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id')->where('account_type', 'customer');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, ?string $term)
    {
        return $query->when($term, fn ($q) => $q->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('nif', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        }));
    }

    public function isCompany(): bool
    {
        return $this->type === 'company' || $this->type === 'fleet';
    }
}
