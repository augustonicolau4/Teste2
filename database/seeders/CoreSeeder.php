<?php

namespace Database\Seeders;

use App\Domain\Core\Enums\DocumentType;
use App\Domain\Core\Enums\ExemptionCode;
use App\Domain\Core\Enums\TaxType;
use App\Domain\Core\Enums\UserRole;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Company;
use App\Domain\Core\Models\Series;
use App\Domain\Core\Models\Setting;
use App\Domain\Core\Models\Tax;
use App\Domain\Core\Models\Unit;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dados-base do autogest_nicola: empresa emitente, filial, unidades,
 * impostos (AGT), séries de faturação, configurações e utilizadores por perfil.
 */
class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $company = $this->company();
        $branch  = $this->branch($company);
        $this->units();
        $this->taxes($company);
        $this->series($company, $branch);
        $this->settings($company);
        $this->users($company, $branch);
    }

    private function company(): Company
    {
        return Company::query()->updateOrCreate(
            ['nif' => '5417000001'],
            [
                'name'               => 'NICOLA AUTO - OFICINA E SERVIÇOS, LDA.',
                'trade_name'         => 'NICOLA AUTO',
                'address'            => 'Rua da Oficina, N.º 12, Bairro Industrial',
                'city'               => 'Luanda',
                'province'           => 'Luanda',
                'country'            => 'AO',
                'phone'              => '+244 938 033 192',
                'email'              => 'geral@nicolaauto.ao',
                'website'            => 'https://nicolaauto.ao',
                'certificate_number' => config('autogest.fiscal.software_certificate_number'),
                'is_default'         => true,
                'is_active'          => true,
            ],
        );
    }

    private function branch(Company $company): Branch
    {
        return Branch::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'SEDE'],
            [
                'name'            => 'Sede - Luanda',
                'address'         => 'Rua da Oficina, N.º 12',
                'city'            => 'Luanda',
                'phone'           => '+244 923 000 000',
                'is_headquarters' => true,
                'is_active'       => true,
            ],
        );
    }

    private function units(): void
    {
        $units = [
            ['code' => 'UN', 'name' => 'Unidade', 'symbol' => 'un'],
            ['code' => 'H',  'name' => 'Hora',    'symbol' => 'h'],
            ['code' => 'KG', 'name' => 'Quilograma', 'symbol' => 'kg'],
            ['code' => 'L',  'name' => 'Litro',   'symbol' => 'l'],
            ['code' => 'MT', 'name' => 'Metro',   'symbol' => 'm'],
            ['code' => 'JG', 'name' => 'Jogo',    'symbol' => 'jg'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(['code' => $unit['code']], $unit);
        }
    }

    private function taxes(Company $company): void
    {
        $taxes = [
            ['code' => 'IVA14', 'name' => 'IVA Normal (14%)',  'type' => TaxType::IVA_NORMAL,   'rate' => 0.14, 'exemption_code' => null],
            ['code' => 'IVA07', 'name' => 'IVA Reduzida (7%)', 'type' => TaxType::IVA_REDUZIDA, 'rate' => 0.07, 'exemption_code' => null],
            ['code' => 'IVA00', 'name' => 'IVA 0%',            'type' => TaxType::IVA_ZERO,     'rate' => 0.00, 'exemption_code' => null],
            ['code' => 'ISE',   'name' => 'Isento de IVA',     'type' => TaxType::ISENTO,       'rate' => 0.00, 'exemption_code' => ExemptionCode::M01],
            ['code' => 'NS',    'name' => 'Não Sujeito',       'type' => TaxType::NAO_SUJEITO,  'rate' => 0.00, 'exemption_code' => ExemptionCode::M20],
        ];

        foreach ($taxes as $tax) {
            Tax::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $tax['code']],
                [
                    'name'           => $tax['name'],
                    'type'           => $tax['type']->value,
                    'rate'           => $tax['rate'],
                    'exemption_code' => $tax['exemption_code']?->value,
                    'is_active'      => true,
                ],
            );
        }
    }

    private function series(Company $company, Branch $branch): void
    {
        $year = (int) now()->year;

        foreach (DocumentType::cases() as $type) {
            $config = config("autogest.series.{$type->value}");

            if (! $config) {
                continue;
            }

            Series::query()->updateOrCreate(
                [
                    'company_id'    => $company->id,
                    'document_type' => $type->value,
                    'code'          => $config['prefix'] . $year,
                    'year'          => $year,
                ],
                [
                    'branch_id'        => $branch->id,
                    'prefix'           => $config['prefix'],
                    'format'           => $config['format'],
                    'padding'          => $config['sequence_padding'] ?? 5,
                    'current_sequence' => 0,
                    'last_number'      => 0,
                    'is_active'        => true,
                ],
            );
        }
    }

    private function settings(Company $company): void
    {
        $settings = [
            ['general', 'company_display_name', 'NICOLA AUTO', 'string'],
            ['general', 'ticket_printer', (string) config('autogest.printing.ticket.default_printer'), 'string'],
            ['fiscal', 'iva_rate', (string) config('autogest.fiscal.iva_rate'), 'float'],
            ['fiscal', 'retention_rate', (string) config('autogest.fiscal.retention_rate'), 'float'],
            ['fiscal', 'software_certificate', (string) config('autogest.fiscal.software_certificate_number'), 'string'],
            ['printing', 'a4_footer', (string) config('autogest.printing.a4.footer'), 'string'],
            ['printing', 'ticket_footer', (string) config('autogest.printing.ticket.footer'), 'string'],
            ['workshop', 'default_labor_rate', '5000', 'float'],
            ['workshop', 'checklist_required', '1', 'bool'],
        ];

        foreach ($settings as [$group, $key, $value, $type]) {
            Setting::query()->updateOrCreate(
                ['company_id' => $company->id, 'group' => $group, 'key' => $key],
                ['value' => $value, 'type' => $type],
            );
        }
    }

    private function users(Company $company, Branch $branch): void
    {
        $users = [
            ['name' => 'Administrador do Sistema', 'email' => 'admin@autogest.co.ao',       'role' => UserRole::ADMIN,           'job_title' => 'Administrador'],
            ['name' => 'Gabriel Gestor',           'email' => 'gestor@autogest.co.ao',      'role' => UserRole::GESTOR,          'job_title' => 'Diretor de Operações'],
            ['name' => 'Fernanda Financeiro',      'email' => 'financeiro@autogest.co.ao',  'role' => UserRole::FINANCEIRO,      'job_title' => 'Contabilista'],
            ['name' => 'Helena RH',                'email' => 'rh@autogest.co.ao',          'role' => UserRole::RH,              'job_title' => 'Gestora de RH'],
            ['name' => 'Alberto Aprovisionador',   'email' => 'stock@autogest.co.ao',       'role' => UserRole::APROVISIONADOR,  'job_title' => 'Responsável de Armazém'],
            ['name' => 'Paulo Promotor',           'email' => 'promotor@autogest.co.ao',    'role' => UserRole::PROMOTOR,        'job_title' => 'Consultor Comercial'],
        ];

        foreach ($users as $data) {
            /** @var User $user */
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'company_id' => $company->id,
                    'branch_id'  => $branch->id,
                    'name'       => $data['name'],
                    'password'   => Hash::make('password'),
                    'phone'      => '+244 9' . random_int(10, 99) . ' ' . random_int(100, 999) . ' ' . random_int(100, 999),
                    'job_title'  => $data['job_title'],
                    'is_active'  => true,
                ],
            );

            $user->syncRoles([$data['role']->value]);
        }
    }
}
