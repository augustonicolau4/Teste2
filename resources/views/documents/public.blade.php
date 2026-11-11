<!DOCTYPE html>
<html lang="pt-AO" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificação · {{ $document->number }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-full bg-slate-100 p-4 font-sans text-slate-800">
@php
    $kz = fn ($v) => number_format((float) $v, 2, ',', '.') . ' Kz';
@endphp

<div class="mx-auto max-w-2xl">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">

        <div class="flex items-start justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Documento verificado</div>
                <h1 class="text-xl font-bold text-slate-900">{{ $document->number }}</h1>
                <p class="text-sm text-slate-500">{{ $document->document_type->label() }}</p>
            </div>
            <span class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-500 font-bold text-slate-900">✓</span>
        </div>

        @unless ($document->is_fiscal)
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">
                DOCUMENTO NÃO FISCAL
            </div>
        @endunless

        <dl class="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-400">Empresa emitente</dt>
                <dd class="font-medium text-slate-900">{{ $document->company?->name }}</dd>
                <dd class="text-slate-500">NIF: {{ $document->company?->nif }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-400">Cliente</dt>
                <dd class="font-medium text-slate-900">{{ $document->customer?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-400">Data de emissão</dt>
                <dd class="font-medium text-slate-900">{{ $document->issue_date?->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-400">Estado</dt>
                <dd class="font-medium text-slate-900">{{ $document->status->label() }}</dd>
            </div>
        </dl>

        <table class="mt-6 w-full text-sm">
            <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="py-2 font-medium">Descrição</th>
                    <th class="py-2 text-right font-medium">Qtd</th>
                    <th class="py-2 text-right font-medium">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($document->lines as $line)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $line->description }}</td>
                        <td class="py-2 text-right text-slate-500">{{ rtrim(rtrim(number_format($line->quantity, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="py-2 text-right font-medium text-slate-900">{{ $kz($line->total_line) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 flex justify-between border-t-2 border-slate-200 pt-3">
            <span class="font-semibold text-slate-700">Total</span>
            <span class="text-lg font-bold text-slate-900">{{ $kz($document->grand_total) }}</span>
        </div>

        <div class="mt-6 rounded-lg bg-slate-50 p-4 text-xs">
            <div class="flex justify-between">
                <span class="text-slate-500">Assinatura digital</span>
                <span class="font-mono font-bold text-slate-900">{{ $document->footerSignature() }}</span>
            </div>
            <div class="mt-1 flex justify-between">
                <span class="text-slate-500">Nº de certificação</span>
                <span class="font-medium text-slate-700">{{ config('autogest.fiscal.software_certificate_number') }}</span>
            </div>
            <div class="mt-2 break-all font-mono text-slate-500">{{ $document->hash }}</div>
        </div>

        <div class="mt-6 flex justify-center">
            <a href="{{ route('documents.public.pdf', $document->uuid) }}"
               class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                Descarregar PDF
            </a>
        </div>
    </div>

    <p class="mt-4 text-center text-[10px] leading-relaxed text-slate-400">
        {{ config('autogest.producer.signature') }}
    </p>
</div>
</body>
</html>
