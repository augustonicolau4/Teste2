<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Models\Document;
use App\Domain\HR\Models\Commission;
use App\Domain\Inventory\Models\Product;
use App\Domain\Workshop\Enums\OrderStatus;
use App\Domain\Workshop\Models\ServiceOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 1);
        $start = now()->startOfMonth();
        $end = now()->toDateString();

        $billed = Document::query()
            ->where('company_id', $companyId)
            ->where('is_fiscal', true)
            ->whereBetween('issue_date', [$start->toDateString(), $end]);

        $ordersByStatus = ServiceOrder::query()
            ->where('company_id', $companyId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $openOrders = ServiceOrder::query()
            ->where('company_id', $companyId)
            ->open()
            ->count();

        $lowStock = Product::query()
            ->where('company_id', $companyId)
            ->where('track_stock', true)
            ->get()
            ->filter(fn (Product $p) => $p->isLowStock())
            ->count();

        return view('dashboard', [
            'kpis' => [
                'billed_month'   => round((clone $billed)->sum('grand_total'), 2),
                'docs_month'     => (clone $billed)->count(),
                'iva_month'      => round((clone $billed)->sum('iva_total'), 2),
                'retention_month' => round((clone $billed)->sum('retention_total'), 2),
                'open_orders'    => $openOrders,
                'low_stock'      => $lowStock,
                'pending_commissions' => round(
                    Commission::query()->where('company_id', $companyId)->pending()->sum('amount'),
                    2
                ),
            ],
            'ordersByStatus' => collect(OrderStatus::cases())
                ->mapWithKeys(fn (OrderStatus $s) => [$s->label() => (int) ($ordersByStatus[$s->value] ?? 0)])
                ->all(),
            'recentDocuments' => Document::query()
                ->with(['customer:id,name'])
                ->where('company_id', $companyId)
                ->latest('id')
                ->limit(6)
                ->get(),
        ]);
    }
}
