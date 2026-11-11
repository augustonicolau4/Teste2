<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Actions\IssueDocumentAction;
use App\Domain\Billing\DTOs\DocumentData;
use App\Domain\Core\Enums\DocumentType;
use App\Domain\Core\Enums\LineType;
use App\Domain\Core\Enums\TaxType;
use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\Vehicle;
use App\Domain\HR\Models\Employee;
use App\Domain\Inventory\Models\Product;
use App\Domain\Workshop\Enums\OrderStatus;
use App\Domain\Workshop\Models\ServiceOrder;
use App\Http\Requests\StoreServiceOrderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceOrderController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 1);

        $orders = ServiceOrder::query()
            ->with(['customer:id,name', 'vehicle:id,plate,model', 'technician:id,name'])
            ->where('company_id', $companyId)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $term) => $q->where('number', 'like', "%{$term}%"))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('service-orders.index', [
            'orders'  => $orders,
            'statuses' => OrderStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 1);

        return view('service-orders.create', [
            'customers'   => Customer::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'vehicles'    => Vehicle::query()->where('company_id', $companyId)->get(['id', 'plate', 'model', 'customer_id']),
            'technicians' => Employee::query()->where('company_id', $companyId)->where('role_type', 'mechanic')
                ->whereNotNull('user_id')->with('user:id,name')->get(),
            'products'    => Product::query()->where('company_id', $companyId)->active()->get(['id', 'name', 'sku', 'sale_price', 'type']),
            'lineTypes'   => collect(LineType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
            'taxTypes'    => collect(TaxType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
        ]);
    }

    public function store(StoreServiceOrderRequest $request): RedirectResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        $number = 'OS-' . str_pad((string) ($this->nextSequence($companyId)), 5, '0', STR_PAD_LEFT);

        $order = ServiceOrder::query()->create([
            'company_id'         => $companyId,
            'branch_id'          => $user->branch_id,
            'number'             => $number,
            'customer_id'        => (int) $request->validated('customer_id'),
            'vehicle_id'         => (int) $request->validated('vehicle_id'),
            'technician_id'      => $request->validated('technician_id'),
            'advisor_id'         => $user->id,
            'status'             => OrderStatus::RECEBIDA->value,
            'entry_at'           => now(),
            'promised_at'        => $request->validated('promised_at'),
            'mileage_in'         => $request->validated('mileage_in'),
            'fuel_level'         => $request->validated('fuel_level'),
            'customer_complaint' => $request->validated('customer_complaint'),
        ]);

        foreach ($request->validated('items') as $item) {
            $order->items()->create([
                'line_type'  => $item['line_type'],
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity'   => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_type'   => $item['tax_type'] ?? TaxType::IVA_NORMAL->value,
                'total'      => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
            ]);
        }

        return redirect()->route('service-orders.show', $order)
            ->with('status', "Ordem de serviço {$order->number} criada.");
    }

    public function show(ServiceOrder $serviceOrder): View
    {
        $serviceOrder->loadMissing(['items.product', 'customer', 'vehicle', 'technician', 'statusHistories', 'document']);

        return view('service-orders.show', [
            'order'       => $serviceOrder,
            'transitions' => $serviceOrder->status->allowedTransitions(),
            'billable'    => $serviceOrder->isBillable(),
        ]);
    }

    public function transition(Request $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'string'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $target = OrderStatus::tryFrom((string) $request->input('status'));

        if (! $target) {
            return back()->with('error', 'Estado inválido.');
        }

        try {
            $serviceOrder->transitionTo($target, $request->user(), $request->input('notes'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Estado atualizado para \"{$target->label()}\".");
    }

    /**
     * Converte a Ordem de Serviço numa Fatura-Recibo (FR).
     */
    public function invoice(Request $request, ServiceOrder $serviceOrder, IssueDocumentAction $action): RedirectResponse
    {
        if (! $serviceOrder->isBillable()) {
            return back()->with('error', 'A OS tem de estar concluída e ainda não faturada.');
        }

        $documentType = DocumentType::tryFrom((string) $request->input('document_type'))
            ?? DocumentType::FATURA_RECIBO;

        $lines = $serviceOrder->items->map(fn ($item) => [
            'description'           => $item->description,
            'quantity'              => (float) $item->quantity,
            'unit_price'            => (float) $item->unit_price,
            'line_type'             => $item->line_type->value,
            'tax_type'              => $item->tax_type->value,
            'product_id'            => $item->product_id,
            'unit_code'             => $item->unit_code ?? 'UN',
            'discount_rate'         => (float) $item->discount_rate,
            'retention_rate'        => $item->retention_applicable ? (float) $item->retention_rate : null,
            'service_order_item_id' => $item->id,
        ])->all();

        $document = $action->execute(new DocumentData(
            companyId: $serviceOrder->company_id,
            documentType: $documentType,
            lines: $lines,
            customerId: $serviceOrder->customer_id,
            vehicleId: $serviceOrder->vehicle_id,
            branchId: $serviceOrder->branch_id,
            serviceOrderId: $serviceOrder->id,
            userId: $request->user()?->id,
            promoterId: $serviceOrder->promoter_id,
            notes: 'Faturação da OS ' . $serviceOrder->number,
        ));

        $serviceOrder->update(['document_id' => $document->id]);

        return redirect()->route('documents.show', $document)
            ->with('status', "Documento {$document->number} emitido a partir da OS {$serviceOrder->number}.");
    }

    private function nextSequence(int $companyId): int
    {
        return ((int) ServiceOrder::query()->where('company_id', $companyId)->max('id')) + 1;
    }
}
