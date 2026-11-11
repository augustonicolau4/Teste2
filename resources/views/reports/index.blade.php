@extends('layouts.app')

@section('title', 'Relatórios')
@section('heading', 'Relatórios Gerenciais e Fiscais')

@php
    $icons = [
        'faturacao'            => 'bg-emerald-500',
        'faturacao-utilizador' => 'bg-sky-500',
        'impostos'             => 'bg-indigo-500',
        'produtividade'        => 'bg-amber-500',
        'stock'                => 'bg-cyan-500',
        'conta-corrente'       => 'bg-violet-500',
        'caixa'                => 'bg-rose-500',
    ];
@endphp

@section('content')
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($catalog as $key => $description)
            <a href="{{ route('reports.show', $key) }}"
               class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                <div class="flex items-start gap-4">
                    <span class="mt-0.5 h-10 w-1.5 rounded-full {{ $icons[$key] ?? 'bg-slate-400' }}"></span>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 group-hover:text-emerald-700">{{ $description }}</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Período configurável · exportação em PDF e Excel
                        </p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @if (auth()->user()?->can('saft.export') || auth()->user()?->isAdmin())
        <div class="rounded-xl border border-slate-200 bg-slate-900 p-6 text-white">
            <h3 class="text-sm font-semibold">Exportação SAF-T AO (XML)</h3>
            <p class="mt-1 text-xs text-slate-400">
                Ficheiro normalizado para entrega à Administração Geral Tributária (AGT).
            </p>
            <form method="GET" action="{{ route('saft.export') }}" class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-slate-400">De</label>
                    <input type="date" name="from" value="{{ now()->startOfYear()->toDateString() }}"
                           class="mt-1 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400">Até</label>
                    <input type="date" name="to" value="{{ now()->toDateString() }}"
                           class="mt-1 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                </div>
                <button class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-emerald-400">
                    Exportar SAF-T
                </button>
            </form>
        </div>
    @endif
@endsection
