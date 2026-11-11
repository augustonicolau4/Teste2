<?php

namespace App\Domain\Core\Enums;

/**
 * Perfis de utilizador (RBAC) - os 6 perfis do autogest_nicola.
 */
enum UserRole: string
{
    case ADMIN           = 'Admin';
    case GESTOR          = 'Gestor';
    case FINANCEIRO      = 'Financeiro';
    case RH              = 'RH';
    case APROVISIONADOR  = 'Aprovisionador';
    case PROMOTOR        = 'Promotor';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN          => 'Administrador',
            self::GESTOR         => 'Gestor',
            self::FINANCEIRO     => 'Financeiro',
            self::RH             => 'Recursos Humanos',
            self::APROVISIONADOR => 'Aprovisionador',
            self::PROMOTOR       => 'Promotor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ADMIN          => 'Acesso total: configurações fiscais, séries, auditoria e utilizadores.',
            self::GESTOR         => 'Visão gerencial, aprovação de orçamentos e supervisão da oficina.',
            self::FINANCEIRO     => 'Faturação, tesouraria, impostos e exportação SAF-T AO.',
            self::RH             => 'Colaboradores, assiduidade, salários (INSS/IRT) e comissões.',
            self::APROVISIONADOR => 'Stock, compras, inventário e atribuição de materiais às OS.',
            self::PROMOTOR       => 'Prospeção, clientes/frotas, orçamentos e comissões de angariação.',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $r) => [$r->value => $r->label()]
        )->all();
    }

    /**
     * Acesso total (Administrador) - recebe todas as permissões existentes.
     */
    public function hasFullAccess(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Matriz de permissões por perfil.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMIN => Permission::cases(),

            // Gestão operacional e supervisão - sem alterações técnicas de sistema.
            self::GESTOR => [
                Permission::DASHBOARD_VIEW,
                Permission::USERS_VIEW,
                Permission::AUDIT_VIEW,
                Permission::CUSTOMERS_VIEW, Permission::CUSTOMERS_MANAGE,
                Permission::FLEETS_MANAGE, Permission::VEHICLES_MANAGE,
                Permission::PRODUCTS_VIEW, Permission::STOCK_VIEW,
                Permission::PURCHASES_VIEW, Permission::PURCHASES_APPROVE,
                Permission::WORKSHOP_VIEW, Permission::WORKSHOP_MANAGE,
                Permission::WORKSHOP_ASSIGN, Permission::WORKSHOP_APPROVE,
                Permission::WORKSHOP_INVOICE,
                Permission::DOCUMENTS_VIEW, Permission::DOCUMENTS_PRINT,
                Permission::PROFORMA_MANAGE,
                Permission::PAYMENTS_VIEW, Permission::CASH_VIEW, Permission::LEDGER_VIEW,
                Permission::EMPLOYEES_VIEW, Permission::PAYROLL_VIEW,
                Permission::COMMISSIONS_VIEW,
                Permission::REPORTS_VIEW, Permission::REPORTS_EXPORT,
                Permission::WHATSAPP_SEND, Permission::AI_USE,
            ],

            // Faturação, tesouraria, impostos e SAF-T AO.
            self::FINANCEIRO => [
                Permission::DASHBOARD_VIEW,
                Permission::CUSTOMERS_VIEW,
                Permission::DOCUMENTS_VIEW, Permission::DOCUMENTS_CREATE,
                Permission::DOCUMENTS_ISSUE, Permission::DOCUMENTS_CANCEL,
                Permission::DOCUMENTS_PRINT, Permission::PROFORMA_MANAGE,
                Permission::PAYMENTS_VIEW, Permission::PAYMENTS_MANAGE,
                Permission::CASH_VIEW, Permission::CASH_MANAGE,
                Permission::LEDGER_VIEW, Permission::TREASURY_MANAGE,
                Permission::COMMISSIONS_VIEW,
                Permission::REPORTS_VIEW, Permission::REPORTS_EXPORT,
                Permission::SAFT_EXPORT,
                Permission::WHATSAPP_SEND, Permission::AI_USE,
            ],

            // Colaboradores, assiduidade, salários (INSS/IRT) e comissões.
            self::RH => [
                Permission::DASHBOARD_VIEW,
                Permission::EMPLOYEES_VIEW, Permission::EMPLOYEES_MANAGE,
                Permission::ATTENDANCE_MANAGE, Permission::LEAVES_MANAGE,
                Permission::PAYROLL_VIEW, Permission::PAYROLL_PROCESS,
                Permission::COMMISSIONS_VIEW, Permission::COMMISSIONS_MANAGE,
                Permission::WORKSHOP_VIEW,
                Permission::REPORTS_VIEW, Permission::REPORTS_EXPORT,
            ],

            // Stock, compras, inventário e atribuição de materiais às OS.
            self::APROVISIONADOR => [
                Permission::DASHBOARD_VIEW,
                Permission::PRODUCTS_VIEW, Permission::PRODUCTS_MANAGE,
                Permission::STOCK_VIEW, Permission::STOCK_MANAGE,
                Permission::SUPPLIERS_MANAGE,
                Permission::PURCHASES_VIEW, Permission::PURCHASES_MANAGE,
                Permission::WORKSHOP_VIEW, Permission::WORKSHOP_ASSIGN,
                Permission::REPORTS_VIEW,
            ],

            // Prospeção, clientes/frotas, orçamentos e comissões de angariação.
            self::PROMOTOR => [
                Permission::DASHBOARD_VIEW,
                Permission::CUSTOMERS_VIEW, Permission::CUSTOMERS_MANAGE,
                Permission::FLEETS_MANAGE, Permission::VEHICLES_MANAGE,
                Permission::PROFORMA_MANAGE,
                Permission::DOCUMENTS_VIEW,
                Permission::WORKSHOP_VIEW,
                Permission::COMMISSIONS_VIEW,
                Permission::REPORTS_VIEW,
                Permission::WHATSAPP_SEND, Permission::AI_USE,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function permissionValues(): array
    {
        return array_map(fn (Permission $p) => $p->value, $this->permissions());
    }
}
