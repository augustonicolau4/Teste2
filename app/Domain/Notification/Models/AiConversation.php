<?php

namespace App\Domain\Notification\Models;

use App\Domain\Core\Models\BaseModel;

class AiConversation extends BaseModel
{
    protected function casts(): array
    {
        return [
            'context'          => 'array',
            'response_time_ms' => 'integer',
        ];
    }
}
