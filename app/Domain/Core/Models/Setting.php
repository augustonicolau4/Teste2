<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends BaseModel
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Lê uma configuração (com cast pelo tipo).
     */
    public static function get(string $group, string $key, mixed $default = null, ?int $companyId = null): mixed
    {
        $row = static::query()
            ->where('group', $group)
            ->where('key', $key)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->first();

        if (! $row) {
            return $default;
        }

        return match ($row->type) {
            'int'   => (int) $row->value,
            'float' => (float) $row->value,
            'bool'  => filter_var($row->value, FILTER_VALIDATE_BOOLEAN),
            'json'  => json_decode((string) $row->value, true),
            default => $row->value,
        };
    }

    public static function put(string $group, string $key, mixed $value, string $type = 'string', ?int $companyId = null): self
    {
        return static::query()->updateOrCreate(
            ['group' => $group, 'key' => $key, 'company_id' => $companyId],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'type' => $type],
        );
    }
}
