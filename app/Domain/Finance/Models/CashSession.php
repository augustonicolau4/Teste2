<?php

namespace App\Domain\Finance\Models;

use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Company;
use App\Domain\Finance\Enums\CashSessionStatus;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends BaseModel
{
    protected function casts(): array
    {
        return [
            'status'           => CashSessionStatus::class,
            'opened_at'        => 'datetime',
            'closed_at'        => 'datetime',
            'opening_balance'  => 'decimal:2',
            'total_in'         => 'decimal:2',
            'total_out'        => 'decimal:2',
            'expected_balance' => 'decimal:2',
            'closing_balance'  => 'decimal:2',
            'difference'       => 'decimal:2',
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

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::ABERTA;
    }
}
