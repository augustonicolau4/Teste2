<?php

namespace App\Domain\HR\Models;

use App\Domain\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends BaseModel
{
    protected function casts(): array
    {
        return [
            'date'           => 'date',
            'worked_hours'   => 'decimal:2',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
