@extends('layouts.app')

@section('title', 'Painel')
@section('heading', 'Painel de Controlo')

@section('content')
    @php
        $kz = fn ($v) => number_format((float) $v, 2, ',', '.') . ' Kz';
    @endphp

    {{-- KPIs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $cards = [
                ['Faturação do mês', $kz($kpis['billed_month']), 'bg-emerald-500'],
                ['Documentos emitidos', number_format($kpis['docs_month'], 0, ',', '.'), 'bg-sky-500'],
                ['IVA liquidado', $kz($kpis['iva_month']), 'bg-indigo-500'],
                ['Retenção na fonte', $kz($kpis['retention_month']), 'bg-amber-500'],
            ];
        @endphp

        @foreach ($cards as [$label, $value, $color])
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</span>
                    <span class="h-2.5 w-2.5 rounded-full {{ $color }}"></span>
                </div>
                <div class="mt-3 text-2xl font-bold text-slate-900">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">OS em aberto</span>
            <div class="mt-3 text-2xl font-bold text-slate-900">{{ $kpis['open_orders'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Alertas de rutura de stock</span>
            <div class="mt-3 text-2xl font-bold {{ $kpis['low_stock'] > 0 ? 'text-red-600' : 'text-slate-900' }}">
                {{ $kpis['low_stock'] }}
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Comissões pendentes</span>
            <div class="mt-3 text-2xl font-bold text-slate-900">{{ $kz($kpis['pending_commissions']) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        {{-- Ordens de serviço por estado --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="text-sm font-semibold text-slate-900">Ordens de Serviço por estado</h2>
            <div class="mt-4 space-y-2">
                @foreach ($ordersByStatus as $label => $total)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">{{ $label }}</span>
                        <span class="font-semibold text-slate-900">{{ $total }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Documentos recentes --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-3">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Documentos recentes</h2>
                <a href="{{ route('documents.index') }}" class="text-xs font-medium text-emerald-600 hover:underline">Ver todos</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-2 font-medium">Documento</th>
                            <th class="px-5 py-2 font-medium">Cliente</th>
                            <th class="px-5 py-2 text-right font-medium">Total</th>
                            <th class="px-5 py-2 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentDocuments as $document)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-2.5">
                                    <a href="{{ route('documents.show', $document) }}" class="font-medium text-emerald-600 hover:underline">
                                        {{ $document->number }}
                                    </a>
                                    <div class="text-xs text-slate-400">{{ $document->document_type->label() }}</div>
                                </td>
                                <td class="px-5 py-2.5 text-slate-600">{{ $document->customer?->name ?? '—' }}</td>
                                <td class="px-5 py-2.5 text-right font-medium text-slate-900">{{ $kz($document->grand_total) }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $document->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-400">
                                    Ainda não foram emitidos documentos.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
