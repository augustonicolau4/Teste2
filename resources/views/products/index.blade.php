@extends('layouts.app')

@section('title', 'Produtos')
@section('heading', 'Produtos & Stock')

@section('content')
    @php
        $kz = fn ($v) => number_format((float) $v, 2, ',', '.') . ' Kz';
    @endphp

    <div x-data="{ showForm: {{ $errors->any() ? 'true' : 'false' }} }" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-end gap-2">
                <div>
                    <label class="block text-xs font-medium text-slate-500">Pesquisar</label>
                    <input name="q" value="{{ request('q') }}" placeholder="Nome, SKU ou OEM"
                           class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Filtrar</button>
            </form>

            @if (auth()->user()?->can('products.manage') || auth()->user()?->isAdmin())
                <button type="button" @click="showForm = true"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    + Novo artigo
                </button>
            @endif
        </div>

        @if ($lowStock->isNotEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-800">
                <strong>{{ $lowStock->count() }}</strong> artigo(s) abaixo do nível de reposição:
                {{ $lowStock->take(5)->pluck('name')->implode(', ') }}@if ($lowStock->count() > 5) …@endif
            </div>
        @endif

        {{-- Formulário --}}
        <div x-cloak x-show="showForm" class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5">
            <form method="POST" action="{{ route('products.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-500">Nome *</label>
                    <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">SKU</label>
                    <input name="sku" value="{{ old('sku') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Código OEM</label>
                    <input name="oem_code" value="{{ old('oem_code') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Tipo</label>
                    <select name="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="part">Peça</option>
                        <option value="product">Produto</option>
                        <option value="consumable">Consumível</option>
                        <option value="service">Serviço</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Preço de custo *</label>
                    <input type="number" step="0.01" min="0" name="cost_price" value="{{ old('cost_price') }}" required
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Preço de venda *</label>
                    <input type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price') }}" required
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">IVA (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="iva_rate" value="{{ old('iva_rate', '14') }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Nível de reposição</label>
                    <input type="number" step="0.001" min="0" name="reorder_level" value="{{ old('reorder_level', '0') }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="flex items-end gap-2 md:col-span-4">
                    <button class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Guardar artigo</button>
                    <button type="button" @click="showForm = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Fechar</button>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">Artigo</th>
                            <th class="px-5 py-3 font-medium">Categoria</th>
                            <th class="px-5 py-3 text-right font-medium">Custo</th>
                            <th class="px-5 py-3 text-right font-medium">Venda</th>
                            <th class="px-5 py-3 text-right font-medium">Margem</th>
                            <th class="px-5 py-3 text-right font-medium">Stock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($products as $product)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-slate-900">{{ $product->name }}</div>
                                    <div class="text-xs text-slate-400">
                                        {{ $product->sku ?? '—' }}@if ($product->oem_code) · OEM {{ $product->oem_code }} @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-slate-500">{{ $product->category?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right text-slate-600">{{ $kz($product->cost_price) }}</td>
                                <td class="px-5 py-3 text-right text-slate-700">{{ $kz($product->sale_price) }}</td>
                                <td class="px-5 py-3 text-right text-slate-500">{{ number_format($product->marginPercent(), 1, ',', '.') }}%</td>
                                <td class="px-5 py-3 text-right">
                                    @php
                                        $stock = $product->stockOnHand();
                                    @endphp
                                    <span class="font-medium {{ $product->isLowStock() ? 'text-red-600' : 'text-slate-900' }}">
                                        {{ rtrim(rtrim(number_format($stock, 3, ',', '.'), '0'), ',') }}
                                    </span>
                                    @if ($product->isLowStock())
                                        <div class="text-[10px] font-medium uppercase text-red-500">Rutura</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">Sem artigos registados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $products->links() }}</div>
    </div>
@endsection
