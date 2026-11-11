<!DOCTYPE html>
<html lang="pt-AO" class="h-full bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · {{ config('autogest.software.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="grid min-h-full place-items-center bg-slate-900 p-4 font-sans">

<div class="w-full max-w-md">
    <div class="mb-6 text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-500 text-2xl font-bold text-slate-900">a</span>
        <h1 class="mt-4 text-2xl font-bold text-white">{{ config('autogest.software.name') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ config('autogest.software.fullname') }}</p>
    </div>

    <div class="rounded-2xl bg-white p-8 shadow-2xl">
        <h2 class="text-lg font-semibold text-slate-900">Iniciar sessão</h2>
        <p class="mt-1 text-sm text-slate-500">Introduza as suas credenciais de acesso.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">E-mail</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username"
                       value="{{ old('email') }}"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Palavra-passe</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                Manter sessão iniciada
            </label>

            <button type="submit"
                    class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
                Entrar
            </button>
        </form>
    </div>

    <p class="mt-6 text-center text-[10px] leading-relaxed text-slate-500">
        {{ config('autogest.producer.signature') }}
    </p>
</div>

</body>
</html>
