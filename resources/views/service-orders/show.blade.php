@extends('layouts.app')

@section('title', $order->number)
@section('heading', 'OS ' . $order->number)

@php
    $kz = fn ($v) => number_format((float) $v, 2, ',', '.') . ' Kz';
@endphp

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('service-orders.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">← Voltar</a>

        @if ($order->document)
            <a href="{{ route('documents.show', $order->document) }}"
               class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Ver documento {{ $order->document->number }}
            </a>
        @elseif ($billable && (auth()->user()?->can('workshop.invoice') || auth()->user()?->isAdmin()))
            <form method="POST" action="{{ route('service-orders.invoice', $order) }}" class="flex items-center gap-2">
                @csrf
                <select name="document_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="FR">Fatura-Recibo (FR)</option>
                    <option value="FT">Fatura (FT)</option>
                </select>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Faturar OS
                </button>
            </form>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Resumo --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-2xl font-bold text-slate-900">{{ $order->number }}</div>
                        <div class="mt-1 text-sm text-slate-500">
                            Entrada: {{ $order->entry_at?->format('d/m/Y H:i') }}
                            @if ($order->promised_at) · Entrega prometida: {{ $order->promised_at->format('d/m/Y') }} @endif
                        </div>
                    </div>
                    <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">
                        {{ $order->status->label() }}
                    </span>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Cliente</div>
                        <div class="mt-1 font-medium text-slate-900">{{ $order->customer?->name ?? '—' }}</div>
                        @if ($order->customer?->phone)
                            <div class="text-sm text-slate-500">Tel: {{ $order->customer->phone }}</div>
                        @endif
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Viatura</div>
                        @if ($order->vehicle)
                            <div class="mt-1 font-medium text-slate-900">{{ $order->vehicle->plate }}</div>
                            <div class="text-sm text-slate-500">{{ $order->vehicle->brand }} {{ $order->vehicle->model }}</div>
                            <div class="text-xs text-slate-400">Km entrada: {{ $order->mileage_in ? number_format($order->mileage_in, 0, ',', '.') : '—' }}</div>
                        @else
                            <div class="mt-1 text-sm text-slate-400">—</div>
                        @endif
                    </div>
                </div>

                @if ($order->customer_complaint)
                    <div class="mt-4 rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm">
                        <div class="text-xs uppercase tracking-wide text-amber-600">Avaria relatada</div>
                        <p class="mt-1 text-slate-700">{{ $order->customer_complaint }}</p>
                    </div>
                @endif
            </div>

            {{-- Itens --}}
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Itens (peças e mão de obra)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">Descrição</th>
                                <th class="px-4 py-3 font-medium">Tipo</th>
                                <th class="px-4 py-3 text-right font-medium">Qtd</th>
                                <th class="px-4 py-3 text-right font-medium">Preço</th>
                                <th class="px-4 py-3 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="px-4 py-3 text-slate-900">{{ $item->description }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                            {{ $item->isLabor() ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $item->line_type->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-600">{{ rtrim(rtrim(number_format($item->quantity, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600">{{ $kz($item->unit_price) }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-slate-900">{{ $kz($item->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Histórico --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Histórico de estados</h2>
                <ol class="mt-4 space-y-3 text-sm">
                    @forelse ($order->statusHistories->sortByDesc('id') as $history)
                        <li class="flex gap-3">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                            <div>
                                <div class="text-slate-700">
                                    {{ $history->from_status }} → <span class="font-medium">{{ $history->to_status }}</span>
                                </div>
                                <div class="text-xs text-slate-400">
                                    {{ $history->created_at?->format('d/m/Y H:i') }}
                                    @if ($history->notes) · {{ $history->notes }} @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="text-slate-400">Sem movimentos registados.</li>
                    @endforelse
                </ol>
            </div>
        </div>

        {{-- Lateral --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Atribuição</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Técnico</dt><dd class="font-medium text-slate-900">{{ $order->technician?->name ?? 'Não atribuído' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Assessor</dt><dd class="font-medium text-slate-900">{{ $order->advisor?->name ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Totais</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Mão de obra</dt><dd class="font-medium text-slate-900">{{ $kz($order->labor_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Peças</dt><dd class="font-medium text-slate-900">{{ $kz($order->parts_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">IVA</dt><dd class="font-medium text-slate-900">{{ $kz($order->iva_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Retenção</dt><dd class="font-medium text-amber-700">{{ $kz($order->retention_total) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2"><dt class="font-semibold text-slate-700">Total</dt><dd class="font-bold text-slate-900">{{ $kz($order->grand_total) }}</dd></div>
                </dl>
            </div>

            @if (! empty($transitions) && (auth()->user()?->can('workshop.manage') || auth()->user()?->isAdmin()))
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900">Alterar estado</h3>
                    <form method="POST" action="{{ route('service-orders.transition', $order) }}" class="mt-3 space-y-3">
                        @csrf
                        <select name="status" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($transitions as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <input name="notes" placeholder="Observações (opcional)"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Atualizar estado
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
