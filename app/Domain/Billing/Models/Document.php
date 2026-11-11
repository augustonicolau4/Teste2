<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoiceRelationType;

use App\Domain\Core\Enums\DocumentStatus;
use App\Domain\Core\Enums\DocumentType;
use App\Domain\Core\Models\BaseModel;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Company;
use App\Domain\Core\Models\Series;
use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\Vehicle;
use App\Domain\Finance\Models\Payment;
use App\Domain\User\Models\User;
use App\Domain\Workshop\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends BaseModel
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            $document->uuid ??= (string) Str::uuid();
            $document->system_entry_date ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'document_type'     => DocumentType::class,
            'status'            => DocumentStatus::class,
            'issue_date'        => 'date',
            'due_date'          => 'date',
            'system_entry_date' => 'datetime',
            'delivered_at'      => 'datetime',
            'cancelled_at'      => 'datetime',
            'saft_exported_at'  => 'datetime',
            'exchange_rate'     => 'decimal:6',
            'gross_total'       => 'decimal:2',
            'discount_total'    => 'decimal:2',
            'net_total'         => 'decimal:2',
            'iva_total'         => 'decimal:2',
            'retention_total'   => 'decimal:2',
            'grand_total'       => 'decimal:2',
            'paid_total'        => 'decimal:2',
            'balance'           => 'decimal:2',
            'is_fiscal'         => 'boolean',
            'printed_count'     => 'integer',
            'sequence'          => 'integer',
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

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoter_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(DocumentTax::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentSignature::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_document_id');
    }

    public function creditNotes(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'document_relations',
            'target_document_id',
            'source_document_id'
        )->wherePivot('relation_type', InvoiceRelationType::CREDIT->value)->withPivot('amount', 'reason');
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    public function scopeFiscal(Builder $query): Builder
    {
        return $query->where('is_fiscal', true);
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DocumentStatus::EMITIDO->value,
            DocumentStatus::PAGO->value,
            DocumentStatus::PARCIAL->value,
        ]);
    }

    public function scopeOfType(Builder $query, DocumentType|string $type): Builder
    {
        return $query->where('document_type', $type instanceof DocumentType ? $type->value : $type);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('issue_date', [$from, $to]);
    }

    // -----------------------------------------------------------------
    // Auxiliares fiscais
    // -----------------------------------------------------------------

    public function isCancelled(): bool
    {
        return $this->status === DocumentStatus::ANULADO;
    }

    public function isIssued(): bool
    {
        return in_array($this->status, [DocumentStatus::EMITIDO, DocumentStatus::PAGO, DocumentStatus::PARCIAL], true);
    }

    /**
     * Assinatura de rodapé: os 4 primeiros caracteres do hash do documento.
     */
    public function footerSignature(): string
    {
        return $this->hash ? strtoupper(substr($this->hash, 0, 4)) : '----';
    }

    /**
     * Conteúdo do QR Code do documento (usado no A4 e no Ticket).
     */
    public function qrPayload(): string
    {
        if (config('autogest.printing.qr.payload') === 'url') {
            return route('documents.public', $this->uuid);
        }

        return implode('*', array_filter([
            'A:' . config('autogest.producer.nif'),
            'B:' . ($this->customer?->nif ?? '999999999'),
            'C:' . config('autogest.fiscal.country'),
            'D:' . $this->document_type->value,
            'E:' . $this->status->value,
            'F:' . $this->issue_date?->format('Ymd'),
            'G:' . number_format((float) $this->grand_total, 2, '.', ''),
            'H:' . ($this->hash_control ? substr($this->hash_control, 0, 4) : '-'),
            'I:' . $this->footerSignature(),
            'J:' . ($this->signature ? 'S' : 'N'),
            'K:' . $this->number,
        ]));
    }

    public function remainingBalance(): float
    {
        // Valor efetivamente a receber = total - retenção na fonte
        // (a retenção é entregue pelo cliente ao Estado, não ao fornecedor).
        $payable = (float) $this->grand_total - (float) $this->retention_total;

        return round($payable - (float) $this->paid_total, 2);
    }
}
