@extends('layouts.app')

@section('title', $report->title)
@section('heading', $report->title)

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <a href="{{ route('reports.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">← Relatórios</a>

        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-500">De</label>
                <input type="date" name="from" value="{{ request('from', now()->startOfMonth()->toDateString()) }}"
                       class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Até</label>
                <input type="date" name="to" value="{{ request('to', now()->toDateString()) }}"
                       class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            @if ($key === 'conta-corrente')
                <div>
                    <label class="block text-xs font-medium text-slate-500">Cliente (ID)</label>
                    <input type="number" name="customer_id" value="{{ request('customer_id', 1) }}"
                           class="mt-1 w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            @endif
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Aplicar</button>
        </form>

        @if (auth()->user()?->can('reports.export') || auth()->user()?->isAdmin())
            <div class="flex gap-2">
                <a href="{{ route('reports.pdf', array_merge(['report' => $key], request()->query())) }}"
                   class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">PDF</a>
                <a href="{{ route('reports.excel', array_merge(['report' => $key], request()->query())) }}"
                   class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</a>
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ $report->title }}</h2>
                <p class="text-xs text-slate-500">
                    @if (isset($report->meta['from']))
                        Período: {{ \Illuminate\Support\Carbon::parse($report->meta['from'])->format('d/m/Y') }}
                        a {{ \Illuminate\Support\Carbon::parse($report->meta['to'])->format('d/m/Y') }}
                    @endif
                </p>
            </div>
            <div class="text-right text-[10px] leading-relaxed text-slate-400">
                {{ config('autogest.software.name') }} v{{ config('autogest.software.version') }}<br>
                Certificação: {{ config('autogest.fiscal.software_certificate_number') }}
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        @foreach ($report->headings as $heading)
                            <th class="px-4 py-3 font-medium">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($report->rows as $row)
                        <tr class="hover:bg-slate-50">
                            @foreach ($row as $cell)
                                <td class="px-4 py-2.5 text-slate-700">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($report->headings) }}" class="px-4 py-12 text-center text-sm text-slate-400">
                                Sem dados para o período selecionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (! empty($report->totals))
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                        @foreach ($report->totals as $label => $value)
                            <tr>
                                <td colspan="{{ max(1, count($report->headings) - 1) }}" class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500">
                                    {{ $label }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-slate-900">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="text-center text-[10px] leading-relaxed text-slate-400">
        {{ config('autogest.producer.signature') }}
    </p>
@endsection
