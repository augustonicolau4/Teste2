<?php

namespace App\Domain\Finance\Models;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Enums\PaymentMethod;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\CRM\Models\Customer;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends BaseModel
{
    protected function casts(): array
    {
        return [
            'method'  => PaymentMethod::class,
            'amount'  => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'in';
    }
}
