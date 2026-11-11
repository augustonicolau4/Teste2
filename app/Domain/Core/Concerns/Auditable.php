<?php

namespace App\Domain\Core\Concerns;

use App\Domain\Core\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Regista automaticamente alterações (created/updated/deleted) na tabela audit_logs.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created'));
        static::updated(fn ($model) => $model->writeAudit('updated', $model->getOriginal()));
        static::deleted(fn ($model) => $model->writeAudit('deleted'));
    }

    protected function writeAudit(string $event, ?array $old = null): void
    {
        if (! config('autogest.audit.enabled', true)) {
            return;
        }

        AuditLog::query()->create([
            'user_id'        => Auth::id(),
            'event'          => $event,
            'auditable_type' => static::class,
            'auditable_id'   => $this->getKey(),
            'old_values'     => $old,
            'new_values'     => $event === 'deleted' ? null : $this->getAttributes(),
            'ip_address'     => request()->ip(),
            'user_agent'     => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
