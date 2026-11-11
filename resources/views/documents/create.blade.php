@extends('layouts.app')

@section('title', 'Emitir documento')
@section('heading', 'Emitir Documento Fiscal')

@php
    $lineTypes = \App\Domain\Core\Enums\LineType::cases();
    $payMethods = \App\Domain\Core\Enums\PaymentMethod::options();
    $exemptions = \App\Domain\Core\Enums\ExemptionCode::options();
@endphp

@section('content')
<form method="POST" action="{{ route('documents.store') }}" class="space-y-6"
      x-data="documentForm()">
    @csrf

    {{-- Dados gerais --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Dados gerais</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">Tipo de documento *</label>
                <select name="document_type" x-model="documentType" required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Cliente *</label>
                <select name="customer_id" x-model.number="customerId" required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— selecionar —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }} @if($customer->nif) (NIF {{ $customer->nif }}) @endif</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Viatura</label>
                <select name="vehicle_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— nenhuma —</option>
                    <template x-for="v in vehiclesForCustomer" :key="v.id">
                        <option :value="v.id" x-text="v.plate + ' — ' + (v.brand||'') + ' ' + (v.model||'')"></option>
                    </template>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}"
                                x-show="!customerId || {{ $vehicle->customer_id }} === customerId"
                                data-customer="{{ $vehicle->customer_id }}">
                            {{ $vehicle->plate }} — {{ $vehicle->brand }} {{ $vehicle->model }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Ordem de Serviço</label>
                <select name="service_order_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— nenhuma —</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}">{{ $order->number }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Data de emissão</label>
                <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Data de vencimento</label>
                <input type="date" name="due_date" value="{{ old('due_date') }}"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
    </div>

    {{-- Linhas --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Linhas do documento</h2>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="deduct_stock" value="1" checked class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                    Dar baixa de stock
                </label>
                <button type="button" @click="addLine()"
                        class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700">
                    + Adicionar linha
                </button>
            </div>
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
                        <th class="px-3 py-2 font-medium">Desc. %</th>
                        <th class="px-3 py-2 font-medium">Ret. %</th>
                        <th class="px-3 py-2 font-medium">Isenção</th>
                        <th class="px-3 py-2 font-medium text-right">Total</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="(line, index) in lines" :key="index">
                        <tr>
                            <td class="px-3 py-2">
                                <input :name="`lines[${index}][description]`" x-model="line.description" required
                                       class="w-48 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <select :name="`lines[${index}][line_type]`" x-model="line.line_type"
                                        @change="line.retention_rate = (line.line_type === 'service' ? 6.5 : 0)"
                                        class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    @foreach ($lineTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <select :name="`lines[${index}][product_id]`" x-model.number="line.product_id"
                                        @change="pickProduct(line)"
                                        class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <option value="">—</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" data-price="{{ $product->sale_price }}" data-type="{{ $product->type }}">
                                            {{ $product->name }} @if($product->sku) [{{ $product->sku }}] @endif
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.001" min="0.001" :name="`lines[${index}][quantity]`"
                                       x-model.number="line.quantity"
                                       class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" :name="`lines[${index}][unit_price]`"
                                       x-model.number="line.unit_price"
                                       class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" max="100" :name="`lines[${index}][discount_rate]`"
                                       x-model.number="line.discount_rate"
                                       class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" max="100" :name="`lines[${index}][retention_rate]`"
                                       x-model.number="line.retention_rate"
                                       class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <select :name="`lines[${index}][exemption_code]`" x-model="line.exemption_code"
                                        class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <option value="">—</option>
                                    @foreach ($exemptions as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2 text-right font-medium text-slate-700"
                                x-text="formatKz(lineTotal(line))"></td>
                            <td class="px-3 py-2">
                                <button type="button" @click="removeLine(index)"
                                        class="text-xs text-red-500 hover:underline">Remover</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-200 bg-slate-50">
                        <td colspan="8" class="px-3 py-3 text-right text-sm font-medium text-slate-600">Total estimado (c/ IVA)</td>
                        <td class="px-3 py-3 text-right text-base font-bold text-slate-900" x-text="formatKz(grandTotal())"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Pagamentos --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Pagamentos</h2>
            <button type="button" @click="addPayment()"
                    class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700">
                + Adicionar pagamento
            </button>
        </div>
        <div class="space-y-3 p-5">
            <template x-for="(payment, index) in payments" :key="index">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Meio</label>
                        <select :name="`payments[${index}][method]`" x-model="payment.method"
                                class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($payMethods as $method => $label)
                                <option value="{{ $method }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Valor</label>
                        <input type="number" step="0.01" min="0" :name="`payments[${index}][amount]`"
                               x-model.number="payment.amount"
                               class="mt-1 w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Referência</label>
                        <input :name="`payments[${index}][reference]`" x-model="payment.reference"
                               class="mt-1 w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <button type="button" @click="payments.splice(index, 1)"
                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs text-red-500 hover:bg-red-50">Remover</button>
                </div>
            </template>
            <p x-show="payments.length === 0" class="text-sm text-slate-400">Sem pagamentos associados (documento a crédito).</p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <label class="block text-xs font-medium text-slate-500">Observações</label>
        <textarea name="notes" rows="2"
                  class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('documents.index') }}" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancelar</a>
        <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
            Emitir e assinar
        </button>
    </div>
</form>

@push('scripts')
<script>
function documentForm() {
    return {
        customerId: '',
        documentType: '{{ old('document_type', 'FR') }}',
        lines: [
            { description: '', line_type: 'service', product_id: '', quantity: 1, unit_price: 0, discount_rate: 0, retention_rate: 6.5, exemption_code: '' }
        ],
        payments: [],
        ivaRates: { product: 0.14, service: 0.14 },
        get vehiclesForCustomer() { return []; },
        addLine() {
            this.lines.push({ description: '', line_type: 'service', product_id: '', quantity: 1, unit_price: 0, discount_rate: 0, retention_rate: 6.5, exemption_code: '' });
        },
        removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
        addPayment() { this.payments.push({ method: 'NU', amount: 0, reference: '' }); },
        pickProduct(line) {
            if (!line.product_id) return;
            const opt = document.querySelector(`option[value="${line.product_id}"][data-price]`);
            if (opt) {
                line.unit_price = parseFloat(opt.dataset.price || 0);
                if (opt.dataset.type && ['part','product','consumable'].includes(opt.dataset.type)) {
                    line.line_type = 'product';
                    line.retention_rate = 0;
                }
            }
        },
        lineNet(line) {
            const gross = (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0);
            return gross - gross * ((parseFloat(line.discount_rate) || 0) / 100);
        },
        lineTotal(line) { return this.lineNet(line) * 1.14; },
        grandTotal() { return this.lines.reduce((sum, l) => sum + this.lineTotal(l), 0); },
        formatKz(v) { return new Intl.NumberFormat('pt-AO', { minimumFractionDigits: 2 }).format(v) + ' Kz'; },
    };
}
</script>
@endpush
@endsection
