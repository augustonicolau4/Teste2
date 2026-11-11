<?php

namespace App\Domain\Billing\Models;

use App\Domain\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignature extends BaseModel
{
    protected function casts(): array
    {
        return [
            'signature_number' => 'integer',
            'signed_at'        => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
