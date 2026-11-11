<?php

namespace App\Domain\HR\Models;

use App\Domain\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends BaseModel
{
    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'is_taxable' => 'boolean',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
