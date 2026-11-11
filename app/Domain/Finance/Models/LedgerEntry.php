<?php

namespace App\Domain\Finance\Models;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\Finance\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends BaseModel
{
    protected function casts(): array
    {
        return [
            'entry_type'    => LedgerEntryType::class,
            'amount'        => 'decimal:2',
            'balance_after' => 'decimal:2',
            'entry_date'    => 'date',
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
