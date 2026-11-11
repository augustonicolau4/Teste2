@extends('layouts.app')

@section('title', $document->number)
@section('heading', 'Documento ' . $document->number)

@php
    $kz = fn ($v) => number_format((float) $v, 2, ',', '.') . ' Kz';
@endphp

@section('content')
    {{-- Ações --}}
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('documents.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">← Voltar</a>

        @if (auth()->user()?->can('documents.print') || auth()->user()?->isAdmin())
            <a href="{{ route('documents.pdf', [$document, 'a4']) }}" target="_blank"
               class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">PDF A4</a>
            <a href="{{ route('documents.pdf', [$document, 'ticket']) }}" target="_blank"
               class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">PDF Ticket</a>
            <form method="POST" action="{{ route('documents.print', [$document, 'a4']) }}" class="inline">
                @csrf
                <button class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Imprimir A4</button>
            </form>
            <form method="POST" action="{{ route('documents.print', [$document, 'ticket']) }}" class="inline">
                @csrf
                <button class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Imprimir Ticket</button>
            </form>
        @endif

        @if ((auth()->user()?->can('whatsapp.send') || auth()->user()?->isAdmin()) && $document->customer?->phone)
            <form method="POST" action="{{ route('documents.whatsapp', $document) }}" class="inline">
                @csrf
                <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Enviar WhatsApp</button>
            </form>
        @endif

        @if ((auth()->user()?->can('documents.cancel') || auth()->user()?->isAdmin())
             && $document->document_type->canBeCredited() && ($document->isIssued() || $document->isCancelled() === false))
            <button type="button" onclick="document.getElementById('cancel-box').classList.toggle('hidden')"
                    class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Anular (Nota de Crédito)</button>
        @endif
    </div>

    @if (auth()->user()?->can('documents.cancel') || auth()->user()?->isAdmin())
        <div id="cancel-box" class="hidden rounded-xl border border-red-200 bg-red-50 p-5">
            <form method="POST" action="{{ route('documents.cancel', $document) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1">
                    <label class="block text-xs font-medium text-red-700">Motivo da anulação</label>
                    <input name="reason" class="mt-1 w-full rounded-lg border border-red-300 px-3 py-2 text-sm" placeholder="Ex.: erro de valores / devolução">
                </div>
                <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Emitir Nota de Crédito</button>
            </form>
        </div>
    @endif

    {{-- Documento --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="lg:col-span-2 space-y-6">
            {{-- Cabeçalho --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            {{ $document->document_type->label() }}
                        </div>
                        <div class="text-2xl font-bold text-slate-900">{{ $document->number }}</div>
                        <div class="mt-1 text-sm text-slate-500">
                            Emitido em {{ $document->issue_date?->format('d/m/Y') }}
                            @if ($document->due_date) · Vence em {{ $document->due_date->format('d/m/Y') }} @endif
                        </div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold
                        {{ $document->isCancelled() ? 'bg-red-100 text-red-700' : ($document->isIssued() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600') }}">
                        {{ $document->status->label() }}
                    </span>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Cliente</div>
                        <div class="mt-1 font-medium text-slate-900">{{ $document->customer?->name ?? '—' }}</div>
                        @if ($document->customer?->nif)
                            <div class="text-sm text-slate-500">NIF: {{ $document->customer->nif }}</div>
                        @endif
                        @if ($document->customer?->phone)
                            <div class="text-sm text-slate-500">Tel: {{ $document->customer->phone }}</div>
                        @endif
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Viatura</div>
                        @if ($document->vehicle)
                            <div class="mt-1 font-medium text-slate-900">{{ $document->vehicle->plate }}</div>
                            <div class="text-sm text-slate-500">{{ $document->vehicle->brand }} {{ $document->vehicle->model }}</div>
                            @if ($document->vehicle->vin)
                                <div class="text-xs text-slate-400">VIN: {{ $document->vehicle->vin }}</div>
                            @endif
                        @else
                            <div class="mt-1 text-sm text-slate-400">—</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Linhas --}}
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">Descrição</th>
                                <th class="px-4 py-3 text-right font-medium">Qtd</th>
                                <th class="px-4 py-3 text-right font-medium">Preço</th>
                                <th class="px-4 py-3 text-right font-medium">Desc.</th>
                                <th class="px-4 py-3 text-right font-medium">IVA</th>
                                <th class="px-4 py-3 text-right font-medium">Retenção</th>
                                <th class="px-4 py-3 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($document->lines as $line)
                                <tr>
                                    <td class="px-4 py-3 text-slate-400">{{ $line->line_number }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-slate-900">{{ $line->description }}</div>
                                        <div class="text-xs text-slate-400">{{ $line->line_type->label() }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-700">{{ rtrim(rtrim(number_format($line->quantity, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">{{ $kz($line->unit_price) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-500">{{ $kz($line->discount_amount) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">{{ $kz($line->iva_amount) }}</td>
                                    <td class="px-4 py-3 text-right text-amber-700">{{ $line->retention_applicable ? $kz($line->retention_amount) : '—' }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-slate-900">{{ $kz($line->total_line) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Assinatura digital --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Assinatura Digital (AGT)</h3>
                <dl class="mt-3 grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-400">Assinatura (4 caracteres do hash)</dt>
                        <dd class="font-mono text-base font-bold text-slate-900">{{ $document->footerSignature() }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Nº de certificação</dt>
                        <dd class="font-medium text-slate-700">{{ config('autogest.fiscal.software_certificate_number') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-400">Hash do documento</dt>
                        <dd class="break-all font-mono text-slate-600">{{ $document->hash ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-400">Hash de controlo (documento anterior)</dt>
                        <dd class="break-all font-mono text-slate-600">{{ $document->hash_control ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Resumo lateral --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Resumo</h3>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Base tributável</dt><dd class="font-medium text-slate-900">{{ $kz($document->net_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Desconto</dt><dd class="font-medium text-slate-900">{{ $kz($document->discount_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">IVA</dt><dd class="font-medium text-slate-900">{{ $kz($document->iva_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Retenção na fonte (6,5%)</dt><dd class="font-medium text-amber-700">−{{ $kz($document->retention_total) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2"><dt class="font-semibold text-slate-700">Total</dt><dd class="font-bold text-slate-900">{{ $kz($document->grand_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Total a pagar</dt><dd class="font-bold text-emerald-700">{{ $kz((float) $document->grand_total - (float) $document->retention_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Liquidado</dt><dd class="font-medium text-slate-900">{{ $kz($document->paid_total) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2"><dt class="font-semibold text-slate-700">Saldo</dt><dd class="font-bold {{ $document->remainingBalance() > 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $kz($document->remainingBalance()) }}</dd></div>
                </dl>
            </div>

            {{-- Mapa de impostos --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Mapa de Impostos</h3>
                <table class="mt-3 w-full text-xs">
                    <thead class="text-left text-slate-400">
                        <tr>
                            <th class="py-1 font-medium">Imposto</th>
                            <th class="py-1 text-right font-medium">Taxa</th>
                            <th class="py-1 text-right font-medium">Base</th>
                            <th class="py-1 text-right font-medium">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($document->taxes as $tax)
                            <tr>
                                <td class="py-1.5 text-slate-700">{{ $tax->description ?? $tax->tax_type->label() }}</td>
                                <td class="py-1.5 text-right text-slate-500">{{ number_format((float) $tax->rate * 100, 2, ',', '.') }}%</td>
                                <td class="py-1.5 text-right text-slate-700">{{ $kz($tax->base_amount) }}</td>
                                <td class="py-1.5 text-right font-medium text-slate-900">{{ $kz($tax->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-center text-slate-400">Sem impostos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagamentos --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Pagamentos</h3>
                <ul class="mt-3 space-y-2 text-xs">
                    @forelse ($document->payments as $payment)
                        <li class="flex justify-between">
                            <span class="text-slate-500">{{ $payment->method->label() }} @if($payment->reference) · {{ $payment->reference }} @endif</span>
                            <span class="font-medium text-slate-900">{{ $kz($payment->amount) }}</span>
                        </li>
                    @empty
                        <li class="text-slate-400">Sem pagamentos registados.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-[10px] leading-relaxed text-slate-500">
                {{ config('autogest.producer.signature') }}
            </div>
        </div>
    </div>
@endsection
