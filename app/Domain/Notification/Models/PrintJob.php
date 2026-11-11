<?php

namespace App\Domain\Notification\Models;

use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrintJob extends BaseModel
{
    protected function casts(): array
    {
        return [
            'copies'     => 'integer',
            'printed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function printable(): MorphTo
    {
        return $this->morphTo();
    }
}
