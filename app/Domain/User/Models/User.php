<?php

namespace App\Domain\User\Models;

use App\Domain\Core\Enums\UserRole;
use App\Domain\Core\Models\AuditLog;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Company;
use App\Domain\HR\Models\Employee;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'email',
        'password',
        'phone',
        'nif',
        'job_title',
        'avatar_path',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'last_login_at'     => 'datetime',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::class;
    }

    // -----------------------------------------------------------------
    // Relações
    // -----------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // -----------------------------------------------------------------
    // Auxiliares (RBAC)
    // -----------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function primaryRole(): ?UserRole
    {
        $name = $this->getRoleNames()->first();

        return $name ? UserRole::tryFrom($name) : null;
    }

    public function roleLabel(): string
    {
        return $this->primaryRole()?->label() ?? 'Sem perfil';
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN->value);
    }

    public function isManager(): bool
    {
        return $this->hasAnyRole([UserRole::ADMIN->value, UserRole::GESTOR->value]);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return strtoupper(
            collect($parts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('')
        );
    }
}
