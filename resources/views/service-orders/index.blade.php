@extends('layouts.app')

@section('title', 'Oficina')
@section('heading', 'Oficina · Ordens de Serviço')

@section('content')
    @php
        $kz = fn ($v) => number_format((float) $v, 2, ',', '.') . ' Kz';
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-500">Nº da OS</label>
                <input name="q" value="{{ request('q') }}" placeholder="OS-00001"
                       class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Estado</label>
                <select name="status" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Filtrar</button>
            <a href="{{ route('service-orders.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Limpar</a>
        </form>

        @if (auth()->user()?->can('workshop.manage') || auth()->user()?->isAdmin())
            <a href="{{ route('service-orders.create') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                + Abrir Ordem de Serviço
            </a>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">OS</th>
                        <th class="px-5 py-3 font-medium">Cliente</th>
                        <th class="px-5 py-3 font-medium">Viatura</th>
                        <th class="px-5 py-3 font-medium">Técnico</th>
                        <th class="px-5 py-3 font-medium">Entrada</th>
                        <th class="px-5 py-3 text-right font-medium">Total</th>
                        <th class="px-5 py-3 font-medium">Estado</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 font-medium text-slate-900">{{ $order->number }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $order->customer?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ $order->vehicle?->plate ?? '—' }}
                                <div class="text-xs text-slate-400">{{ $order->vehicle?->model }}</div>
                            </td>
                            <td class="px-5 py-3 text-slate-500">{{ $order->technician?->name ?? 'Não atribuído' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $order->entry_at?->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-right font-medium text-slate-900">{{ $kz($order->grand_total) }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $order->status->value === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700' }}">
                                    {{ $order->status->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('service-orders.show', $order) }}" class="text-xs font-medium text-emerald-600 hover:underline">Abrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-sm text-slate-400">Sem ordens de serviço.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $orders->links() }}</div>
@endsection
