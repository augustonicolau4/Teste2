<?php

namespace Database\Seeders;

use App\Domain\Billing\Actions\IssueDocumentAction;
use App\Domain\Billing\DTOs\DocumentData;
use App\Domain\Core\Enums\DocumentType;
use App\Domain\Core\Enums\PaymentMethod;
use App\Domain\Core\Models\Company;
use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\Vehicle;
use App\Domain\HR\Models\Employee;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductCategory;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Domain\User\Models\User;
use App\Domain\Workshop\Enums\OrderStatus;
use App\Domain\Workshop\Models\ServiceOrder;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração: clientes, viaturas, armazém, produtos, fornecedores,
 * colaboradores, ordens de serviço e documentos fiscais emitidos pelo motor.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::current();
        if (! $company) {
            return;
        }

        $branch      = $company->branches()->first();
        $warehouse   = $this->warehouse($company, $branch);
        $categories  = $this->categories($company);
        $products    = $this->products($company, $categories, $warehouse);
        $this->suppliers($company);
        $employees   = $this->employees($company, $branch);

        $promoter = User::query()->where('email', 'promotor@autogest.co.ao')->first();
        $advisor  = User::query()->where('email', 'gestor@autogest.co.ao')->first();

        $customers = $this->customers($company, $promoter);
        $vehicles  = $this->vehicles($company, $customers);

        $this->serviceOrders($company, $branch, $customers, $vehicles, $employees, $advisor);

        // Emissão fiscal de demonstração (Fatura-Recibo pago via Multicaixa).
        $this->issueDemoInvoice($company, $branch, $customers, $vehicles, $promoter);
    }

    private function warehouse(Company $company, $branch): Warehouse
    {
        return Warehouse::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'ARM1'],
            [
                'branch_id'  => $branch?->id,
                'name'       => 'Armazém Central',
                'location'   => 'Luanda - Bairro Industrial',
                'is_default' => true,
                'is_active'  => true,
            ],
        );
    }

    /**
     * @return array<string, ProductCategory>
     */
    private function categories(Company $company): array
    {
        $defs = [
            'parts'   => 'Peças',
            'lubes'   => 'Lubrificantes',
            'labor'   => 'Mão de Obra',
        ];

        $result = [];
        foreach ($defs as $key => $name) {
            $result[$key] = ProductCategory::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => strtoupper($key)],
                ['name' => $name, 'is_active' => true],
            );
        }

        return $result;
    }

    /**
     * @param  array<string, ProductCategory>  $categories
     * @return array<string, Product>
     */
    private function products(Company $company, array $categories, Warehouse $warehouse): array
    {
        $defs = [
            // code => [name, type, cost, price, oem, category, iva, stock_min]
            'FLT-OIL'  => ['Filtro de Óleo', 'part', 8500, 15000, 'OEM-90915-YZZE1', 'parts', 0.14, 10],
            'FLT-AIR'  => ['Filtro de Ar', 'part', 12000, 21000, 'OEM-17801-0N040', 'parts', 0.14, 8],
            'BRK-PAD'  => ['Pastilhas de Travão (jogo)', 'part', 28000, 45000, 'OEM-04465-0K290', 'parts', 0.14, 5],
            'OIL-5W30' => ['Óleo Motor 5W30 (5L)', 'consumable', 22000, 38000, 'LUB-5W30-5L', 'lubes', 0.14, 12],
            'MO-BAS'   => ['Mão de Obra - Manutenção Base', 'service', 0, 12000, null, 'labor', 0.14, 0],
            'MO-DIAG'  => ['Diagnóstico Eletrónico', 'service', 0, 18000, null, 'labor', 0.14, 0],
        ];

        $stock = app(StockService::class);
        $result = [];

        foreach ($defs as $sku => [$name, $type, $cost, $price, $oem, $cat, $iva, $min]) {
            /** @var Product $product */
            $product = Product::query()->updateOrCreate(
                ['company_id' => $company->id, 'sku' => $sku],
                [
                    'category_id'   => $categories[$cat]->id,
                    'oem_code'      => $oem,
                    'name'          => $name,
                    'type'          => $type,
                    'cost_price'    => $cost,
                    'sale_price'    => $price,
                    'iva_rate'      => $iva,
                    'retention_applicable' => $type === 'service',
                    'stock_min'     => $min,
                    'reorder_level' => $min,
                    'track_stock'   => $type !== 'service',
                    'is_active'     => true,
                ],
            );

            // Carga inicial de stock para artigos com controlo de inventário.
            if ($product->track_stock && $product->movements()->count() === 0) {
                $stock->entry($company->id, $product, max($min * 3, 20), [
                    'warehouse' => $warehouse,
                    'reason'    => 'Carga inicial de inventário',
                    'unit_cost' => $cost,
                ]);
            }

            $result[$sku] = $product;
        }

        return $result;
    }

    private function suppliers(Company $company): void
    {
        $defs = [
            ['name' => 'AUTO PEÇAS LUANDA, LDA.', 'nif' => '5411000222', 'phone' => '+244 923 111 222'],
            ['name' => 'LUBRIFICANTES ANGOLA, SA', 'nif' => '5411000333', 'phone' => '+244 924 333 444'],
        ];

        foreach ($defs as $def) {
            Supplier::query()->updateOrCreate(
                ['company_id' => $company->id, 'nif' => $def['nif']],
                $def + ['country' => 'AO', 'is_active' => true],
            );
        }
    }

    /**
     * @return array<string, Employee>
     */
    private function employees(Company $company, $branch): array
    {
        $defs = [
            'mechanic' => [
                'name' => 'Carlos Mecânico', 'role_type' => 'mechanic', 'position' => 'Mecânico Sénior',
                'base_salary' => 350_000, 'commission_rate' => 0.05,
                'email' => 'carlos.mecanico@nicolaauto.ao',
            ],
            'mechanic2' => [
                'name' => 'Mário Técnico', 'role_type' => 'mechanic', 'position' => 'Técnico de Mecânica',
                'base_salary' => 280_000, 'commission_rate' => 0.04,
                'email' => 'mario.tecnico@nicolaauto.ao',
            ],
            'promoter' => [
                'name' => 'Paulo Promotor', 'role_type' => 'promoter', 'position' => 'Consultor Comercial',
                'base_salary' => 250_000, 'commission_rate' => 0.03,
                'email' => 'promotor@autogest.co.ao',
            ],
        ];

        $result = [];
        foreach ($defs as $key => $def) {
            $user = User::query()->where('email', $def['email'])->first();

            $result[$key] = Employee::query()->updateOrCreate(
                ['company_id' => $company->id, 'email' => $def['email']],
                [
                    'branch_id'            => $branch?->id,
                    'user_id'              => $user?->id,
                    'name'                 => $def['name'],
                    'position'             => $def['position'],
                    'role_type'            => $def['role_type'],
                    'hire_date'            => now()->subYears(2)->toDateString(),
                    'base_salary'          => $def['base_salary'],
                    'meal_allowance'       => 30_000,
                    'transport_allowance'  => 25_000,
                    'inss_employee_rate'   => 0.03,
                    'inss_employer_rate'   => 0.08,
                    'commission_rate'      => $def['commission_rate'],
                    'is_active'            => true,
                ],
            );
        }

        return $result;
    }

    /**
     * @return array<int, Customer>
     */
    private function customers(Company $company, ?User $promoter): array
    {
        $defs = [
            [
                'name' => 'TRANSPORTES BENGO, LDA.', 'type' => 'fleet', 'nif' => '5412000111',
                'phone' => '+244 923 555 111', 'city' => 'Luanda', 'email' => 'frota@transportesbengo.ao',
                'credit_limit' => 5_000_000,
            ],
            [
                'name' => 'João Manuel dos Santos', 'type' => 'individual', 'nif' => '006123456LA041',
                'phone' => '+244 924 777 222', 'city' => 'Luanda', 'email' => 'joao.santos@gmail.com',
                'credit_limit' => 0,
            ],
            [
                'name' => 'CONSTRUÇÕES KALUNGA, SA', 'type' => 'company', 'nif' => '5413000222',
                'phone' => '+244 925 888 333', 'city' => 'Benguela', 'email' => 'compras@kalunga.co.ao',
                'credit_limit' => 10_000_000,
            ],
        ];

        $customers = [];
        foreach ($defs as $i => $def) {
            $customers[] = Customer::query()->updateOrCreate(
                ['company_id' => $company->id, 'nif' => $def['nif']],
                $def + [
                    'code'        => 'CL' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'country'     => 'AO',
                    'promoter_id' => $promoter?->id,
                    'is_active'   => true,
                ],
            );
        }

        return $customers;
    }

    /**
     * @param  array<int, Customer>  $customers
     * @return array<int, Vehicle>
     */
    private function vehicles(Company $company, array $customers): array
    {
        $defs = [
            ['plate' => 'LD-45-67-AB', 'customer' => 0, 'brand' => 'Toyota', 'model' => 'Hilux',   'year' => 2021, 'km' => 87450, 'fuel' => 'gasoleo',  'vin' => 'AHTFR22G50',
                'insurance' => now()->addMonths(4), 'inspection' => now()->addMonths(2)],
            ['plate' => 'LD-12-34-CD', 'customer' => 0, 'brand' => 'Mitsubishi', 'model' => 'L200', 'year' => 2019, 'km' => 152300, 'fuel' => 'gasoleo', 'vin' => 'MMBJNKB40',
                'insurance' => now()->addMonth(), 'inspection' => now()->subDays(10)],
            ['plate' => 'LD-98-76-EF', 'customer' => 1, 'brand' => 'Hyundai', 'model' => 'i10',       'year' => 2020, 'km' => 62300, 'fuel' => 'gasolina', 'vin' => 'MALBA51CAA',
                'insurance' => now()->addMonths(6), 'inspection' => now()->addMonths(5)],
        ];

        $vehicles = [];
        foreach ($defs as $def) {
            $customer = $customers[$def['customer']];

            $vehicles[] = Vehicle::query()->updateOrCreate(
                ['company_id' => $company->id, 'plate' => $def['plate']],
                [
                    'customer_id'       => $customer->id,
                    'vin'               => $def['vin'],
                    'brand'             => $def['brand'],
                    'model'             => $def['model'],
                    'year'              => $def['year'],
                    'color'             => 'Branco',
                    'fuel_type'         => $def['fuel'],
                    'current_km'        => $def['km'],
                    'next_service_km'   => $def['km'] + 10000,
                    'insurance_expiry'  => $def['insurance']->toDateString(),
                    'inspection_expiry' => $def['inspection']->toDateString(),
                    'is_active'         => true,
                ],
            );
        }

        return $vehicles;
    }

    /**
     * @param  array<int, Customer>  $customers
     * @param  array<int, Vehicle>  $vehicles
     * @param  array<string, Employee>  $employees
     */
    private function serviceOrders(Company $company, $branch, array $customers, array $vehicles, array $employees, ?User $advisor): void
    {
        $defs = [
            [
                'number' => 'OS-' . now()->year . '-00001',
                'customer' => 0, 'vehicle' => 0, 'status' => OrderStatus::EM_EXECUCAO,
                'technician' => $employees['mechanic']->user_id,
                'complaint' => 'Revisão geral e substituição de filtros. Ruído no motor.',
                'mileage_in' => 87450, 'fuel' => '1/2',
            ],
            [
                'number' => 'OS-' . now()->year . '-00002',
                'customer' => 1, 'vehicle' => 2, 'status' => OrderStatus::CONCLUIDA,
                'technician' => $employees['mechanic2']->user_id,
                'complaint' => 'Substituição de pastilhas de travão e diagnóstico eletrónico.',
                'mileage_in' => 62300, 'fuel' => '3/4',
            ],
        ];

        foreach ($defs as $def) {
            /** @var ServiceOrder $order */
            $order = ServiceOrder::query()->updateOrCreate(
                ['company_id' => $company->id, 'number' => $def['number']],
                [
                    'branch_id'          => $branch?->id,
                    'customer_id'        => $customers[$def['customer']]->id,
                    'vehicle_id'         => $vehicles[$def['vehicle']]->id,
                    'advisor_id'         => $advisor?->id,
                    'technician_id'      => $def['technician'],
                    'promoter_id'        => $customers[$def['customer']]->promoter_id,
                    'status'             => $def['status']->value,
                    'priority'           => 'normal',
                    'type'               => 'maintenance',
                    'mileage_in'         => $def['mileage_in'],
                    'fuel_level'         => $def['fuel'],
                    'customer_complaint' => $def['complaint'],
                    'entry_at'           => now()->subDays(2),
                ],
            );

            if ($order->items()->count() === 0) {
                $items = [
                    ['line_type' => 'service', 'description' => 'Mão de Obra - Manutenção Base', 'quantity' => 2, 'unit_price' => 12000, 'technician_id' => $def['technician']],
                    ['line_type' => 'product', 'description' => 'Filtro de Óleo', 'quantity' => 1, 'unit_price' => 15000],
                    ['line_type' => 'product', 'description' => 'Óleo Motor 5W30 (5L)', 'quantity' => 2, 'unit_price' => 38000],
                ];

                $labor = 0.0;
                $parts = 0.0;
                $net = 0.0;
                $iva = 0.0;

                foreach ($items as $item) {
                    $lineNet = round($item['quantity'] * $item['unit_price'], 2);
                    $lineIva = round($lineNet * 0.14, 2);

                    if ($item['line_type'] === 'service') {
                        $labor += $lineNet;
                    } else {
                        $parts += $lineNet;
                    }
                    $net += $lineNet;
                    $iva += $lineIva;

                    $order->items()->create($item + [
                        'tax_type'   => 'IVA14',
                        'iva_rate'   => 0.14,
                        'total'      => round($lineNet + $lineIva, 2),
                        'labor_hours' => $item['line_type'] === 'service' ? 2 : 0,
                    ]);
                }

                $order->update([
                    'labor_total'   => round($labor, 2),
                    'parts_total'   => round($parts, 2),
                    'net_total'     => round($net, 2),
                    'iva_total'     => round($iva, 2),
                    'grand_total'   => round($net + $iva, 2),
                ]);
            }
        }
    }

    /**
     * @param  array<int, Customer>  $customers
     * @param  array<int, Vehicle>  $vehicles
     */
    private function issueDemoInvoice(Company $company, $branch, array $customers, array $vehicles, ?User $promoter): void
    {
        // Emite apenas se ainda não existir nenhuma fatura.
        if (\App\Domain\Billing\Models\Document::query()->where('company_id', $company->id)->exists()) {
            return;
        }

        $customer = $customers[0];
        $vehicle  = $vehicles[0];
        $finance  = User::query()->where('email', 'financeiro@autogest.co.ao')->first();

        $lines = [
            ['line_type' => 'service', 'description' => 'Mão de Obra - Manutenção Base', 'quantity' => 3, 'unit_price' => 12000, 'tax_type' => 'IVA14'],
            ['line_type' => 'product', 'description' => 'Filtro de Óleo', 'quantity' => 1, 'unit_price' => 15000, 'tax_type' => 'IVA14'],
            ['line_type' => 'product', 'description' => 'Óleo Motor 5W30 (5L)', 'quantity' => 2, 'unit_price' => 38000, 'tax_type' => 'IVA14'],
        ];

        $total = 0.0;
        $retention = 0.0;
        foreach ($lines as $l) {
            $lineNet = $l['quantity'] * $l['unit_price'];
            $total += $lineNet;
            // Retenção na fonte (6,5%) apenas sobre serviços / mão de obra.
            if ($l['line_type'] === 'service') {
                $retention += $lineNet * (float) config('autogest.fiscal.retention_rate', 0.065);
            }
        }
        $grand = round($total * 1.14, 2);
        $payable = round($grand - round($retention, 2), 2);

        app(IssueDocumentAction::class)->execute(new DocumentData(
            companyId: $company->id,
            documentType: DocumentType::FATURA_RECIBO,
            lines: $lines,
            customerId: $customer->id,
            vehicleId: $vehicle->id,
            branchId: $branch?->id,
            userId: $finance?->id,
            promoterId: $promoter?->id,
            notes: 'Documento de demonstração emitido pelo seeder.',
            payments: [
                ['method' => PaymentMethod::MULTICAIXA->value, 'amount' => $payable, 'reference' => 'TPA-000123'],
            ],
        ));
    }
}
