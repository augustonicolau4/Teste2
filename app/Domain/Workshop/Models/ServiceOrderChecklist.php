<?php

namespace App\Domain\Workshop\Models;

use App\Domain\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderChecklist extends BaseModel
{
    protected function casts(): array
    {
        return ['is_ok' => 'boolean'];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
