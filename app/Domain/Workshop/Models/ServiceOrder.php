<?php

namespace App\Domain\Workshop\Models;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Company;
use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\Fleet;
use App\Domain\CRM\Models\Vehicle;
use App\Domain\User\Models\User;
use App\Domain\Workshop\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use RuntimeException;

class ServiceOrder extends BaseModel
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (ServiceOrder $order) {
            $order->uuid ??= (string) Str::uuid();
            $order->entry_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'status'            => OrderStatus::class,
            'entry_at'          => 'datetime',
            'promised_at'       => 'datetime',
            'finished_at'       => 'datetime',
            'delivered_at'      => 'datetime',
            'quote_sent_at'     => 'datetime',
            'quote_approved_at' => 'datetime',
            'mileage_in'        => 'integer',
            'mileage_out'       => 'integer',
            'labor_total'       => 'decimal:2',
            'parts_total'       => 'decimal:2',
            'discount_total'    => 'decimal:2',
            'net_total'         => 'decimal:2',
            'iva_total'         => 'decimal:2',
            'retention_total'   => 'decimal:2',
            'grand_total'       => 'decimal:2',
        ];
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoter_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklist::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ServiceOrderStatusHistory::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ServiceOrderMaterial::class);
    }

    public function laborItems(): HasMany
    {
        return $this->items()->where('line_type', 'service');
    }

    public function partItems(): HasMany
    {
        return $this->items()->where('line_type', 'product');
    }

    // -----------------------------------------------------------------
    // Fluxo de estados
    // -----------------------------------------------------------------

    public function canTransitionTo(OrderStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    public function transitionTo(OrderStatus $target, ?User $user = null, ?string $notes = null): self
    {
        if (! $this->canTransitionTo($target)) {
            throw new RuntimeException(
                "Transição de estado inválida: {$this->status->value} -> {$target->value}"
            );
        }

        $from = $this->status;

        $this->status = $target;
        $this->finished_at = $target === OrderStatus::CONCLUIDA ? now() : $this->finished_at;
        $this->delivered_at = $target === OrderStatus::ENTREGUE ? now() : $this->delivered_at;
        $this->save();

        $this->statusHistories()->create([
            'from_status' => $from->value,
            'to_status'   => $target->value,
            'user_id'     => $user?->id,
            'notes'       => $notes,
        ]);

        return $this;
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            OrderStatus::ENTREGUE->value,
            OrderStatus::CANCELADA->value,
        ]);
    }

    public function scopeBillable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::CONCLUIDA->value,
            OrderStatus::ENTREGUE->value,
        ])->whereNull('document_id');
    }

    public function isBillable(): bool
    {
        return $this->status->isBillable() && $this->document_id === null;
    }
}
