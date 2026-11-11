<?php

namespace App\Domain\HR\Models;

use App\Domain\Core\Enums\UserRole;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Company;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'hire_date'            => 'date',
            'termination_date'     => 'date',
            'birth_date'           => 'date',
            'base_salary'          => 'decimal:2',
            'meal_allowance'       => 'decimal:2',
            'transport_allowance'  => 'decimal:2',
            'other_allowances'     => 'decimal:2',
            'inss_employee_rate'   => 'decimal:4',
            'inss_employer_rate'   => 'decimal:4',
            'commission_rate'      => 'decimal:4',
            'is_active'            => 'boolean',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function isMechanic(): bool
    {
        return $this->role_type === 'mechanic';
    }

    public function isPromoter(): bool
    {
        return $this->role_type === 'promoter';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRole($query, string $role)
    {
        return $query->where('role_type', $role);
    }
}
