<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoiceRelationType;
use App\Domain\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRelation extends BaseModel
{
    protected function casts(): array
    {
        return [
            'relation_type' => InvoiceRelationType::class,
            'amount'        => 'decimal:2',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }
}
