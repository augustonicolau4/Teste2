<?php

namespace App\Domain\HR\Services;

use App\Domain\Billing\Models\Document;
use App\Domain\Core\Enums\UserRole;
use App\Domain\HR\Enums\CommissionStatus;
use App\Domain\HR\Models\Commission;
use App\Domain\HR\Models\Employee;
use App\Domain\User\Models\User;
use App\Domain\Workshop\Models\ServiceOrder;

/**
 * Cálculo de comissões de mecânicos (mão de obra) e promotores (angariação).
 */
class CommissionService
{
    /**
     * Comissão do promotor pela emissão de um documento fiscal.
     */
    public function forDocument(Document $document): ?Commission
    {
        $promoterId = $document->promoter_id ?? $document->customer?->promoter_id;

        if (! $promoterId) {
            return null;
        }

        $rate = $this->promoterRate($promoterId);
        $base = (float) $document->net_total;

        return Commission::query()->create([
            'company_id'       => $document->company_id,
            'user_id'          => $promoterId,
            'employee_id'      => optional(Employee::query()->where('user_id', $promoterId)->first())->id,
            'role'             => 'promoter',
            'source_type'      => Document::class,
            'source_id'        => $document->id,
            'document_id'      => $document->id,
            'base_amount'      => $base,
            'rate'             => $rate,
            'amount'           => round($base * $rate, 2),
            'reference_period' => $document->issue_date?->format('Y-m'),
            'status'           => CommissionStatus::PENDENTE->value,
        ]);
    }

    /**
     * Comissões dos técnicos pela mão de obra executada numa Ordem de Serviço.
     *
     * @return array<int, Commission>
     */
    public function forServiceOrder(ServiceOrder $order): array
    {
        $created = [];

        foreach ($order->laborItems()->get() as $item) {
            if (! $item->technician_id) {
                continue;
            }

            $rate = $this->technicianRate($item->technician_id);
            $base = (float) $item->total;

            $created[] = Commission::query()->create([
                'company_id'       => $order->company_id,
                'user_id'          => $item->technician_id,
                'employee_id'      => optional(Employee::query()->where('user_id', $item->technician_id)->first())->id,
                'role'             => 'technician',
                'source_type'      => ServiceOrder::class,
                'source_id'        => $order->id,
                'base_amount'      => $base,
                'rate'             => $rate,
                'amount'           => round($base * $rate, 2),
                'reference_period' => ($order->finished_at ?? now())->format('Y-m'),
                'status'           => CommissionStatus::PENDENTE->value,
            ]);
        }

        return $created;
    }

    private function promoterRate(int $userId): float
    {
        $rate = Employee::query()->where('user_id', $userId)->value('commission_rate');

        return $rate !== null
            ? (float) $rate
            : (float) config('autogest.commissions.promoter_default_rate', 0.03);
    }

    private function technicianRate(int $userId): float
    {
        $rate = Employee::query()->where('user_id', $userId)->value('commission_rate');

        return $rate !== null
            ? (float) $rate
            : (float) config('autogest.commissions.technician_default_rate', 0.05);
    }

    /**
     * Total de comissões de um período (referência YYYY-MM).
     */
    public function periodTotal(int $companyId, string $period): float
    {
        return (float) Commission::query()
            ->where('company_id', $companyId)
            ->where('reference_period', $period)
            ->whereNot('status', CommissionStatus::ANULADA->value)
            ->sum('amount');
    }
}
