<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Enums\ExemptionCode;
use App\Domain\Core\Enums\TaxType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tax extends BaseModel
{
    protected function casts(): array
    {
        return [
            'type'           => TaxType::class,
            'exemption_code' => ExemptionCode::class,
            'rate'           => 'decimal:4',
            'is_active'      => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
