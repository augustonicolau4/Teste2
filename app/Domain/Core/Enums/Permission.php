<?php

namespace App\Domain\Core\Enums;

/**
 * Permissões granulares do autogest_nicola.
 *
 * Convenção: "{modulo}.{acao}". O conjunto completo é sincronizado para a
 * tabela `permissions` (spatie/laravel-permission) e agregado por perfil
 * através de UserRole::permissions().
 */
enum Permission: string
{
    // Painel
    case DASHBOARD_VIEW       = 'dashboard.view';

    // Sistema / Administração
    case USERS_VIEW           = 'users.view';
    case USERS_MANAGE         = 'users.manage';
    case ROLES_MANAGE         = 'roles.manage';
    case SETTINGS_MANAGE      = 'settings.manage';
    case COMPANIES_MANAGE     = 'companies.manage';
    case BRANCHES_MANAGE      = 'branches.manage';
    case SERIES_MANAGE        = 'series.manage';
    case TAXES_MANAGE         = 'taxes.manage';
    case AUDIT_VIEW           = 'audit.view';

    // CRM
    case CUSTOMERS_VIEW       = 'customers.view';
    case CUSTOMERS_MANAGE     = 'customers.manage';
    case FLEETS_MANAGE        = 'fleets.manage';
    case VEHICLES_MANAGE      = 'vehicles.manage';

    // Stock / Aprovisionamento
    case PRODUCTS_VIEW        = 'products.view';
    case PRODUCTS_MANAGE      = 'products.manage';
    case STOCK_VIEW           = 'stock.view';
    case STOCK_MANAGE         = 'stock.manage';
    case SUPPLIERS_MANAGE     = 'suppliers.manage';
    case PURCHASES_VIEW       = 'purchases.view';
    case PURCHASES_MANAGE     = 'purchases.manage';
    case PURCHASES_APPROVE    = 'purchases.approve';

    // Oficina / Ordens de Serviço
    case WORKSHOP_VIEW        = 'workshop.view';
    case WORKSHOP_MANAGE      = 'workshop.manage';
    case WORKSHOP_ASSIGN      = 'workshop.assign';
    case WORKSHOP_APPROVE     = 'workshop.approve';
    case WORKSHOP_INVOICE     = 'workshop.invoice';

    // Faturação
    case DOCUMENTS_VIEW       = 'documents.view';
    case DOCUMENTS_CREATE     = 'documents.create';
    case DOCUMENTS_ISSUE      = 'documents.issue';
    case DOCUMENTS_CANCEL     = 'documents.cancel';
    case DOCUMENTS_PRINT      = 'documents.print';
    case PROFORMA_MANAGE      = 'proforma.manage';

    // Finanças / Tesouraria
    case PAYMENTS_VIEW        = 'payments.view';
    case PAYMENTS_MANAGE      = 'payments.manage';
    case CASH_VIEW            = 'cash.view';
    case CASH_MANAGE          = 'cash.manage';
    case LEDGER_VIEW          = 'ledger.view';
    case TREASURY_MANAGE      = 'treasury.manage';

    // Recursos Humanos
    case EMPLOYEES_VIEW       = 'employees.view';
    case EMPLOYEES_MANAGE     = 'employees.manage';
    case ATTENDANCE_MANAGE    = 'attendance.manage';
    case LEAVES_MANAGE        = 'leaves.manage';
    case PAYROLL_VIEW         = 'payroll.view';
    case PAYROLL_PROCESS      = 'payroll.process';
    case COMMISSIONS_VIEW     = 'commissions.view';
    case COMMISSIONS_MANAGE   = 'commissions.manage';

    // Relatórios e Fisco
    case REPORTS_VIEW         = 'reports.view';
    case REPORTS_EXPORT       = 'reports.export';
    case SAFT_EXPORT          = 'saft.export';

    // Integrações (WhatsApp / IA)
    case WHATSAPP_SEND        = 'whatsapp.send';
    case AI_USE               = 'ai.use';
    case INTEGRATIONS_MANAGE  = 'integrations.manage';

    public function module(): string
    {
        return explode('.', $this->value)[0];
    }

    public function label(): string
    {
        return match ($this) {
            self::DASHBOARD_VIEW      => 'Ver painel de controlo',
            self::USERS_VIEW          => 'Consultar utilizadores',
            self::USERS_MANAGE        => 'Gerir utilizadores',
            self::ROLES_MANAGE        => 'Gerir perfis e permissões',
            self::SETTINGS_MANAGE     => 'Gerir configurações',
            self::COMPANIES_MANAGE    => 'Gerir empresas',
            self::BRANCHES_MANAGE     => 'Gerir filiais',
            self::SERIES_MANAGE       => 'Gerir séries de faturação',
            self::TAXES_MANAGE        => 'Gerir impostos',
            self::AUDIT_VIEW          => 'Consultar auditoria',
            self::CUSTOMERS_VIEW      => 'Consultar clientes/frotas',
            self::CUSTOMERS_MANAGE    => 'Gerir clientes',
            self::FLEETS_MANAGE       => 'Gerir frotas',
            self::VEHICLES_MANAGE     => 'Gerir viaturas',
            self::PRODUCTS_VIEW       => 'Consultar produtos/peças',
            self::PRODUCTS_MANAGE     => 'Gerir produtos/peças',
            self::STOCK_VIEW          => 'Consultar stock',
            self::STOCK_MANAGE        => 'Gerir stock e inventário',
            self::SUPPLIERS_MANAGE    => 'Gerir fornecedores',
            self::PURCHASES_VIEW      => 'Consultar compras',
            self::PURCHASES_MANAGE    => 'Gerir compras',
            self::PURCHASES_APPROVE   => 'Aprovar compras',
            self::WORKSHOP_VIEW       => 'Consultar ordens de serviço',
            self::WORKSHOP_MANAGE     => 'Gerir ordens de serviço',
            self::WORKSHOP_ASSIGN     => 'Atribuir técnicos e materiais',
            self::WORKSHOP_APPROVE    => 'Aprovar orçamentos',
            self::WORKSHOP_INVOICE    => 'Converter OS em fatura',
            self::DOCUMENTS_VIEW      => 'Consultar documentos fiscais',
            self::DOCUMENTS_CREATE    => 'Criar documentos',
            self::DOCUMENTS_ISSUE     => 'Emitir documentos fiscais',
            self::DOCUMENTS_CANCEL    => 'Anular documentos (Nota de Crédito)',
            self::DOCUMENTS_PRINT     => 'Imprimir (A4/Ticket)',
            self::PROFORMA_MANAGE     => 'Gerir orçamentos/pró-forma',
            self::PAYMENTS_VIEW       => 'Consultar pagamentos',
            self::PAYMENTS_MANAGE     => 'Registar pagamentos',
            self::CASH_VIEW           => 'Consultar caixa',
            self::CASH_MANAGE         => 'Abrir/fechar caixa',
            self::LEDGER_VIEW         => 'Consultar conta-corrente',
            self::TREASURY_MANAGE     => 'Gerir tesouraria',
            self::EMPLOYEES_VIEW      => 'Consultar colaboradores',
            self::EMPLOYEES_MANAGE    => 'Gerir colaboradores',
            self::ATTENDANCE_MANAGE   => 'Gerir assiduidade',
            self::LEAVES_MANAGE       => 'Gerir férias e licenças',
            self::PAYROLL_VIEW        => 'Consultar folha salarial',
            self::PAYROLL_PROCESS     => 'Processar salários (INSS/IRT)',
            self::COMMISSIONS_VIEW    => 'Consultar comissões',
            self::COMMISSIONS_MANAGE  => 'Gerir comissões',
            self::REPORTS_VIEW        => 'Consultar relatórios',
            self::REPORTS_EXPORT      => 'Exportar relatórios (PDF/Excel)',
            self::SAFT_EXPORT         => 'Exportar SAF-T AO',
            self::WHATSAPP_SEND       => 'Enviar mensagens WhatsApp',
            self::AI_USE              => 'Utilizar assistente de IA',
            self::INTEGRATIONS_MANAGE => 'Gerir integrações (WhatsApp/IA)',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * Agrupamento por módulo, usado na UI de gestão de perfis.
     *
     * @return array<string, array<int, self>>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $permission) {
            $groups[$permission->module()][] = $permission;
        }

        return $groups;
    }
}
