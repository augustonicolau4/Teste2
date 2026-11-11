<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 1);

        $products = Product::query()
            ->with('category:id,name')
            ->where('company_id', $companyId)
            ->search($request->query('q'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'lowStock' => Product::query()
                ->where('company_id', $companyId)
                ->where('track_stock', true)
                ->get()
                ->filter(fn (Product $p) => $p->isLowStock()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'sku'          => ['nullable', 'string', 'max:60'],
            'oem_code'     => ['nullable', 'string', 'max:60'],
            'type'         => ['nullable', 'string', 'max:20'],
            'cost_price'   => ['required', 'numeric', 'min:0'],
            'sale_price'   => ['required', 'numeric', 'min:0'],
            'iva_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock_min'    => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_id'      => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $data['company_id'] = (int) $request->user()->company_id;
        $data['iva_rate'] = isset($data['iva_rate']) ? (float) $data['iva_rate'] / 100 : 0.14;
        $data['track_stock'] = true;

        $product = Product::query()->create($data);

        return redirect()->route('products.index')
            ->with('status', "Artigo {$product->name} registado com sucesso.");
    }
}
