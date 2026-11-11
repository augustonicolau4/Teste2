<?php

namespace App\Domain\HR\Models;

use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\HR\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends BaseModel
{
    protected function casts(): array
    {
        return [
            'status'              => PayrollStatus::class,
            'worked_days'         => 'integer',
            'base_salary'         => 'decimal:2',
            'overtime_amount'     => 'decimal:2',
            'meal_allowance'      => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'other_earnings'      => 'decimal:2',
            'gross_total'         => 'decimal:2',
            'taxable_base'        => 'decimal:2',
            'inss_employee'       => 'decimal:2',
            'inss_employer'       => 'decimal:2',
            'irt'                 => 'decimal:2',
            'other_deductions'    => 'decimal:2',
            'net_salary'          => 'decimal:2',
            'processed_at'        => 'datetime',
            'paid_at'             => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * Custo total para a entidade empregadora (salário líquido + INSS patronal + IRT).
     */
    public function employerCost(): float
    {
        return round((float) $this->gross_total + (float) $this->inss_employer, 2);
    }
}
