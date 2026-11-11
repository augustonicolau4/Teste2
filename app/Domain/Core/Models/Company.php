<?php

namespace App\Domain\Core\Models;

use App\Domain\Billing\Models\Document;
use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\Vehicle;
use App\Domain\Inventory\Models\Product;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Company extends BaseModel
{
    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            $company->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function current(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->where('is_active', true)->first();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
