@extends('layouts.app')

@section('title', 'Nova OS')
@section('heading', 'Abrir Ordem de Serviço')

@section('content')
<form method="POST" action="{{ route('service-orders.store') }}" class="space-y-6" x-data="serviceOrderForm()">
    @csrf

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Recepção da viatura</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">Cliente *</label>
                <select name="customer_id" x-model.number="customerId" required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— selecionar —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Viatura *</label>
                <select name="vehicle_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— selecionar —</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" x-show="!customerId || {{ $vehicle->customer_id }} === customerId">
                            {{ $vehicle->plate }} — {{ $vehicle->model }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Técnico responsável</label>
                <select name="technician_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— atribuir depois —</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->user_id }}">{{ $technician->user?->name ?? $technician->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Quilometragem (km)</label>
                <input type="number" name="mileage_in" min="0" value="{{ old('mileage_in') }}"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Nível de combustível (%)</label>
                <input type="number" name="fuel_level" min="0" max="100" value="{{ old('fuel_level') }}"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Entrega prometida</label>
                <input type="date" name="promised_at" value="{{ old('promised_at') }}"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-medium text-slate-500">Avaria relatada pelo cliente</label>
                <textarea name="customer_complaint" rows="2"
                          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('customer_complaint') }}</textarea>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Diagnóstico / Trabalhos a executar</h2>
            <button type="button" @click="addItem()"
                    class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700">
                + Adicionar item
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">Descrição</th>
                        <th class="px-3 py-2 font-medium">Tipo</th>
                        <th class="px-3 py-2 font-medium">Artigo</th>
                        <th class="px-3 py-2 font-medium">Qtd</th>
                        <th class="px-3 py-2 font-medium">Preço unit.</th>
                        <th class="px-3 py-2 font-medium">IVA</th>
                        <th class="px-3 py-2 text-right font-medium">Total</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="(item, index) in items" :key="index">
                        <tr>
                            <td class="px-3 py-2">
                                <input :name="`items[${index}][description]`" x-model="item.description" required
                                       class="w-48 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <select :name="`items[${index}][line_type]`" x-model="item.line_type"
                                        class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    @foreach ($lineTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <select :name="`items[${index}][product_id]`" x-model.number="item.product_id"
                                        @change="pickProduct(item)"
                                        class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <option value="">—</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" data-price="{{ $product->sale_price }}" data-type="{{ $product->type }}">
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.001" min="0.001" :name="`items[${index}][quantity]`"
                                       x-model.number="item.quantity" class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`"
                                       x-model.number="item.unit_price" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <select :name="`items[${index}][tax_type]`" x-model="item.tax_type"
                                        class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    @foreach ($taxTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2 text-right font-medium text-slate-700" x-text="formatKz(itemTotal(item))"></td>
                            <td class="px-3 py-2">
                                <button type="button" @click="removeItem(index)" class="text-xs text-red-500 hover:underline">Remover</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('service-orders.index') }}" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancelar</a>
        <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
            Abrir OS
        </button>
    </div>
</form>

@push('scripts')
<script>
function serviceOrderForm() {
    return {
        customerId: '',
        items: [{ description: '', line_type: 'service', product_id: '', quantity: 1, unit_price: 0, tax_type: 'IVA14' }],
        addItem() { this.items.push({ description: '', line_type: 'service', product_id: '', quantity: 1, unit_price: 0, tax_type: 'IVA14' }); },
        removeItem(i) { if (this.items.length > 1) this.items.splice(i, 1); },
        pickProduct(item) {
            if (!item.product_id) return;
            const opt = document.querySelector(`option[value="${item.product_id}"][data-price]`);
            if (opt) {
                item.unit_price = parseFloat(opt.dataset.price || 0);
                if (['part','product','consumable'].includes(opt.dataset.type || '')) item.line_type = 'product';
            }
        },
        itemTotal(item) {
            const base = (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
            const rate = item.tax_type === 'IVA07' ? 0.07 : (item.tax_type === 'IVA14' ? 0.14 : 0);
            return base * (1 + rate);
        },
        formatKz(v) { return new Intl.NumberFormat('pt-AO', { minimumFractionDigits: 2 }).format(v) + ' Kz'; },
    };
}
</script>
@endpush
@endsection
