@extends('layouts.app')

@section('title', 'Documentos')
@section('heading', 'Documentos Fiscais')

@section('content')
    @php
        $kz = fn ($v) => number_format((float) $v, 2, ',', '.') . ' Kz';
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-500">Pesquisar</label>
                <input name="q" value="{{ request('q') }}" placeholder="Nº ou hash"
                       class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Tipo</label>
                <select name="type" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">De</label>
                <input type="date" name="from" value="{{ request('from') }}"
                       class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Até</label>
                <input type="date" name="to" value="{{ request('to') }}"
                       class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Filtrar</button>
            <a href="{{ route('documents.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Limpar</a>
        </form>

        @if (auth()->user()?->can('documents.create') || auth()->user()?->isAdmin())
            <a href="{{ route('documents.create') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                + Emitir documento
            </a>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Documento</th>
                        <th class="px-5 py-3 font-medium">Cliente</th>
                        <th class="px-5 py-3 font-medium">Viatura</th>
                        <th class="px-5 py-3 font-medium">Data</th>
                        <th class="px-5 py-3 text-right font-medium">Base</th>
                        <th class="px-5 py-3 text-right font-medium">IVA</th>
                        <th class="px-5 py-3 text-right font-medium">Retenção</th>
                        <th class="px-5 py-3 text-right font-medium">Total</th>
                        <th class="px-5 py-3 font-medium">Estado</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($documents as $document)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <div class="font-medium text-slate-900">{{ $document->number }}</div>
                                <div class="text-xs text-slate-400">{{ $document->document_type->label() }}</div>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $document->customer?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $document->vehicle?->plate ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $document->issue_date?->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-right text-slate-700">{{ $kz($document->net_total) }}</td>
                            <td class="px-5 py-3 text-right text-slate-700">{{ $kz($document->iva_total) }}</td>
                            <td class="px-5 py-3 text-right text-amber-700">{{ $kz($document->retention_total) }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-slate-900">{{ $kz($document->grand_total) }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $document->isCancelled() ? 'bg-red-100 text-red-700' : ($document->isIssued() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $document->status->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('documents.show', $document) }}" class="text-xs font-medium text-emerald-600 hover:underline">Abrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-12 text-center text-sm text-slate-400">Sem documentos para os filtros aplicados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $documents->links() }}</div>
@endsection
