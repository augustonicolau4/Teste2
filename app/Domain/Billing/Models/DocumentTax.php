<?php

namespace App\Domain\Billing\Models;

use App\Domain\Core\Enums\ExemptionCode;
use App\Domain\Core\Enums\TaxType;
use App\Domain\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTax extends BaseModel
{
    protected function casts(): array
    {
        return [
            'tax_type'      => TaxType::class,
            'exemption_code' => ExemptionCode::class,
            'base_amount'   => 'decimal:2',
            'rate'          => 'decimal:4',
            'amount'        => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
