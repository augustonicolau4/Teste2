<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Core\Enums\LineType;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Kardex / movimentação de stock.
 * Todas as quantidades são persistidas com sinal: entradas positivas, saídas negativas.
 */
class StockService
{
    public function record(
        int $companyId,
        Product $product,
        MovementType $type,
        float $quantity,
        array $options = [],
    ): StockMovement {
        /** @var Warehouse|null $warehouse */
        $warehouse = $options['warehouse'] ?? null;
        $warehouseId = $warehouse?->id ?? ($options['warehouse_id'] ?? $this->defaultWarehouseId($companyId));

        $signed = match ($type) {
            MovementType::ENTRADA => abs($quantity),
            MovementType::SAIDA   => -abs($quantity),
            default               => $quantity, // ajuste/transferência: valor já assinado
        };

        $before = $product->stockOnHand($warehouseId);
        $after = round($before + $signed, 3);
        $unitCost = (float) ($options['unit_cost'] ?? $product->cost_price);

        $movement = new StockMovement([
            'company_id'   => $companyId,
            'product_id'   => $product->id,
            'warehouse_id' => $warehouseId,
            'type'         => $type->value,
            'quantity'     => $signed,
            'unit_cost'    => $unitCost,
            'total_cost'   => round(abs($signed) * $unitCost, 2),
            'stock_before' => $before,
            'stock_after'  => $after,
            'reason'       => $options['reason'] ?? null,
            'moved_at'     => $options['moved_at'] ?? now(),
        ]);

        if (isset($options['user']) && $options['user'] instanceof User) {
            $movement->user()->associate($options['user']);
        } elseif (isset($options['user_id'])) {
            $movement->user_id = $options['user_id'];
        }

        if (isset($options['source']) && $options['source'] instanceof Model) {
            $movement->source()->associate($options['source']);
        }

        $movement->save();

        return $movement;
    }

    public function entry(int $companyId, Product $product, float $quantity, array $options = []): StockMovement
    {
        return $this->record($companyId, $product, MovementType::ENTRADA, $quantity, $options);
    }

    public function exit(int $companyId, Product $product, float $quantity, array $options = []): StockMovement
    {
        return $this->record($companyId, $product, MovementType::SAIDA, $quantity, $options);
    }

    public function adjust(int $companyId, Product $product, float $newQuantity, array $options = []): StockMovement
    {
        $current = $product->stockOnHand($options['warehouse_id'] ?? null);
        $delta = $newQuantity - $current;

        return $this->record($companyId, $product, MovementType::AJUSTE, $delta, $options + ['reason' => 'Ajuste de inventário']);
    }

    /**
     * Produtos abaixo do ponto de encomenda (alertas de rutura).
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function lowStockProducts(int $companyId, ?int $limit = null)
    {
        $query = Product::query()
            ->where('company_id', $companyId)
            ->where('track_stock', true)
            ->where('is_active', true)
            ->withSum('movements as stock_on_hand', 'quantity')
            ->orderBy('name');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()
            ->filter(fn (Product $p) => (float) ($p->stock_on_hand ?? 0) <= (float) $p->reorder_level)
            ->values();
    }

    public function defaultWarehouseId(int $companyId): ?int
    {
        return Warehouse::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->value('id');
    }
}
