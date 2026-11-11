<?php

namespace App\Domain\Notification\Models;

use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Company;
use App\Domain\Notification\Enums\MessageDirection;
use App\Domain\Notification\Enums\MessageStatus;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsAppMessage extends BaseModel
{
    protected $table = 'whatsapp_messages';

    protected function casts(): array
    {
        return [
            'direction'    => MessageDirection::class,
            'status'       => MessageStatus::class,
            'payload'      => 'array',
            'sent_at'      => 'datetime',
            'delivered_at' => 'datetime',
            'read_at'      => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', MessageDirection::INBOUND->value);
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', MessageDirection::OUTBOUND->value);
    }
}
