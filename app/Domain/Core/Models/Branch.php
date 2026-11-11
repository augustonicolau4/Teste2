<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends BaseModel
{
    protected function casts(): array
    {
        return [
            'is_headquarters' => 'boolean',
            'is_active'       => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
