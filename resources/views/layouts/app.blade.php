<!DOCTYPE html>
<html lang="pt-AO" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') · {{ config('autogest.software.name') }}</title>

    {{-- Assets compilados pelo Vite (Tailwind CSS 4 + JS). --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
    @stack('head')
</head>
<body class="h-full font-sans text-slate-800 antialiased"
      x-data="{ sidebarOpen: false }">

@php
    $user = auth()->user();
@endphp

<div class="min-h-full lg:flex">

    {{-- ================= Sidebar ================= --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 transform bg-slate-900 text-slate-300 transition-transform duration-200 lg:static lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

        <div class="flex h-16 items-center gap-2 border-b border-slate-800 px-5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-500 font-bold text-slate-900">a</span>
            <div class="leading-tight">
                <div class="text-sm font-semibold text-white">{{ config('autogest.software.name') }}</div>
                <div class="text-[10px] uppercase tracking-wide text-slate-500">Oficina &amp; Faturação</div>
            </div>
        </div>

        <nav class="space-y-1 overflow-y-auto px-3 py-4 text-sm">
            @php
                $nav = [
                    ['dashboard', 'Painel', 'dashboard.view'],
                    ['documents.index', 'Documentos', 'documents.view'],
                    ['service-orders.index', 'Oficina / OS', 'workshop.view'],
                    ['customers.index', 'Clientes', 'customers.view'],
                    ['products.index', 'Produtos & Stock', 'products.view'],
                    ['reports.index', 'Relatórios', 'reports.view'],
                ];

                $icons = [
                    'dashboard' => 'M3 12l9-9 9 9M5 10v10h14V10',
                    'documents.index' => 'M7 3h7l5 5v13H7z M14 3v5h5',
                    'service-orders.index' => 'M12 6v6l4 2 M12 3a9 9 0 100 18 9 9 0 000-18z',
                    'customers.index' => 'M16 14a4 4 0 10-8 0 M12 7a3 3 0 100 6 3 3 0 000-6z M4 21a8 8 0 0116 0',
                    'products.index' => 'M3 7l9-4 9 4-9 4-9-4z M3 7v10l9 4 9-4V7',
                    'reports.index' => 'M4 20V10 M10 20V4 M16 20v-7 M22 20H2',
                ];

                // Aliases de rota para o estado ativo (ex.: documentos.show destaca documentos.index).
                $current = request()->route()?->getName() ?? '';
            @endphp

            @foreach ($nav as [$route, $label, $permission])
                @if (Route::has($route) && ($user?->can($permission) || $user?->isAdmin()))
                    @php
                        $module = strtok($route, '.');
                    @endphp
                    <a href="{{ route($route) }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2 transition
                              {{ str_starts_with($current, $module) ? 'bg-emerald-600 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$route] }}"/>
                        </svg>
                        <span>{{ $label }}</span>
                    </a>
                @endif
            @endforeach

            @if ($user?->can('saft.export') || $user?->isAdmin())
                <div class="pt-3">
                    <a href="{{ route('saft.export') }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2 text-xs uppercase tracking-wide text-slate-500 transition hover:bg-slate-800 hover:text-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16"/>
                        </svg>
                        Exportar SAF-T AO
                    </a>
                </div>
            @endif
        </nav>

        <div class="absolute bottom-0 left-0 right-0 border-t border-slate-800 p-4 text-[10px] leading-relaxed text-slate-500">
            {{ config('autogest.software.name') }} v{{ config('autogest.software.version') }}<br>
            NICOLA DA TEC (SU), LDA. · NIF {{ config('autogest.producer.nif') }}
        </div>
    </aside>

    {{-- Overlay (mobile) --}}
    <div x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

    {{-- ================= Conteúdo ================= --}}
    <div class="flex min-w-0 flex-1 flex-col">

        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:px-8">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = true" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1 class="text-lg font-semibold text-slate-900">@yield('heading', 'Painel')</h1>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden text-right sm:block">
                    <div class="text-sm font-medium text-slate-900">{{ $user?->name }}</div>
                    <div class="text-xs text-slate-500">{{ $user?->roleLabel() }}</div>
                </div>
                <span class="grid h-9 w-9 place-items-center rounded-full bg-slate-900 text-xs font-bold text-white">
                    {{ $user?->initials() }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        Sair
                    </button>
                </form>
            </div>
        </header>

        <main class="flex-1 space-y-6 px-4 py-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white px-4 py-3 text-center text-[10px] leading-relaxed text-slate-400 lg:px-8">
            {{ config('autogest.producer.signature') }}
        </footer>
    </div>
</div>

@stack('scripts')
</body>
</html>
