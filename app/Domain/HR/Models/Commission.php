<?php

namespace App\Domain\HR\Models;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\HR\Enums\CommissionStatus;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Commission extends BaseModel
{
    protected function casts(): array
    {
        return [
            'status'           => CommissionStatus::class,
            'base_amount'      => 'decimal:2',
            'rate'             => 'decimal:4',
            'amount'           => 'decimal:2',
            'approved_at'      => 'datetime',
            'paid_at'          => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending($query)
    {
        return $query->where('status', CommissionStatus::PENDENTE->value);
    }

    public function scopePeriod($query, string $period)
    {
        return $query->where('reference_period', $period);
    }
}
