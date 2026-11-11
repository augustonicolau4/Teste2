<?php

namespace App\Domain\Billing\DTOs;

use App\Domain\Core\Enums\DocumentType;

/**
 * Dados de entrada para emissão de um documento fiscal.
 */
final readonly class DocumentData
{
    /**
     * @param array<int, array{
     *   description:string,
     *   quantity:float|int,
     *   unit_price:float|int,
     *   line_type?:string,
     *   tax_type?:string,
     *   discount_rate?:float,
     *   discount_amount?:float,
     *   retention_rate?:float,
     *   exemption_code?:string|null,
     *   product_id?:int|null,
     *   unit_code?:string|null,
     *   service_order_item_id?:int|null,
     *   notes?:string|null
     * }> $lines
     * @param array<int, array{method:string, amount:float, reference?:string|null}> $payments
     */
    public function __construct(
        public int $companyId,
        public DocumentType $documentType,
        public array $lines,
        public ?int $customerId = null,
        public ?int $vehicleId = null,
        public ?int $branchId = null,
        public ?int $serviceOrderId = null,
        public ?int $userId = null,
        public ?int $promoterId = null,
        public ?string $issueDate = null,
        public ?string $dueDate = null,
        public ?string $notes = null,
        public bool $deductStock = true,
        public array $payments = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            companyId: (int) $data['company_id'],
            documentType: $data['document_type'] instanceof DocumentType
                ? $data['document_type']
                : DocumentType::from($data['document_type']),
            lines: $data['lines'] ?? [],
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            vehicleId: isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            branchId: isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            serviceOrderId: isset($data['service_order_id']) ? (int) $data['service_order_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            promoterId: isset($data['promoter_id']) ? (int) $data['promoter_id'] : null,
            issueDate: $data['issue_date'] ?? null,
            dueDate: $data['due_date'] ?? null,
            notes: $data['notes'] ?? null,
            deductStock: $data['deduct_stock'] ?? true,
            payments: $data['payments'] ?? [],
        );
    }
}
