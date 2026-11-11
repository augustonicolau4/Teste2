<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="utf-8">
    <title>{{ $document->number }}</title>
    <style>
        @page { margin: 14mm 12mm 20mm 12mm; }

        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9px; color: #111827; margin: 0; }

        .muted { color: #6b7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; }

        /* Cabeçalho */
        .header { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 8px; }
        .header td { vertical-align: top; }
        .logo { max-height: 58px; margin-bottom: 4px; }
        .company-name { font-size: 13px; font-weight: bold; }
        .doc-box { border: 1px solid #111827; padding: 6px 8px; text-align: center; }
        .doc-box .type { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
        .doc-box .number { font-size: 11px; margin-top: 2px; }
        .doc-box .status { margin-top: 4px; font-size: 9px; }

        .watermark-nonfiscal {
            border: 1px solid #b91c1c; color: #b91c1c; text-align: center;
            padding: 3px; font-weight: bold; margin-bottom: 6px; font-size: 10px;
        }

        /* Blocos de informação */
        .info-block { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        .info-title { font-size: 8px; text-transform: uppercase; color: #6b7280; margin-bottom: 3px; font-weight: bold; }
        .info-row { margin-bottom: 2px; }

        /* Tabela de linhas */
        .lines { margin-top: 8px; }
        .lines th { background: #111827; color: #ffffff; font-size: 8px; padding: 4px 5px; text-align: left; }
        .lines td { border-bottom: 1px solid #e5e7eb; padding: 4px 5px; }
        .lines td.num { text-align: right; }

        /* Totais */
        .totals td { padding: 2px 6px; }
        .totals .grand { font-size: 11px; font-weight: bold; border-top: 2px solid #111827; }

        .words { border: 1px solid #d1d5db; padding: 5px 6px; margin-top: 6px; font-style: italic; }

        .taxes th { background: #f3f4f6; font-size: 8px; padding: 3px 5px; border: 1px solid #d1d5db; }
        .taxes td { font-size: 8px; padding: 3px 5px; border: 1px solid #d1d5db; }

        /* Rodapé fiscal */
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; border-top: 1px solid #9ca3af; padding-top: 4px; font-size: 7px; color: #4b5563; }
        .footer .sig { font-weight: bold; color: #111827; }
    </style>
</head>
<body>

<!-- Cabeçalho -->
<table class="header">
    <tr>
        <td style="width: 55%;">
            @if ($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}" alt="logotipo">
            @endif
            <div class="company-name">{{ $company->name }}</div>
            <div class="info-row">NIF: {{ $company->nif }}</div>
            <div class="info-row">{{ $company->address }}@if($company->city) , {{ $company->city }}@endif</div>
            <div class="info-row">
                @if ($company->phone) Tel: {{ $company->phone }} @endif
                @if ($company->email) | {{ $company->email }} @endif
            </div>
        </td>
        <td style="width: 25%;" class="right">
            @if ($qrDataUri)
                <img src="{{ $qrDataUri }}" width="92" height="92" alt="QR">
            @endif
        </td>
        <td style="width: 20%;">
            <div class="doc-box">
                <div class="type">{{ $document->document_type->label() }}</div>
                <div class="number">{{ $document->number }}</div>
                <div class="status muted">{{ $document->status->label() }}</div>
            </div>
        </td>
    </tr>
</table>

@unless ($document->is_fiscal)
    <div class="watermark-nonfiscal">DOCUMENTO NÃO FISCAL - SEM VALOR DE FATURA (PRÓ-FORMA / ORÇAMENTO)</div>
@endunless

<!-- Cliente / Viatura / Documento -->
<table>
    <tr>
        <td style="width: 50%; padding-right: 4px;">
            <div class="info-block">
                <div class="info-title">Exmo(a). Sr(a). Cliente</div>
                <div class="info-row bold">{{ $document->customer?->name ?? 'Consumidor Final' }}</div>
                @if ($document->customer?->nif) <div class="info-row">NIF: {{ $document->customer->nif }}</div> @endif
                @if ($document->customer?->address) <div class="info-row">{{ $document->customer->address }}@if($document->customer->city), {{ $document->customer->city }}@endif</div> @endif
                @if ($document->customer?->phone) <div class="info-row">Tel: {{ $document->customer->phone }}</div> @endif
            </div>
        </td>
        <td style="width: 50%; padding-left: 4px;">
            <div class="info-block">
                <div class="info-title">Viatura / Dados da Operação</div>
                <table>
                    <tr>
                        <td style="width: 50%;">
                            <div class="info-row">Matrícula: <span class="bold">{{ $document->vehicle?->plate ?? 'N/D' }}</span></div>
                            <div class="info-row">Marca/Modelo: {{ $document->vehicle?->brand }} {{ $document->vehicle?->model }}</div>
                            <div class="info-row">VIN: {{ $document->vehicle?->vin ?? 'N/D' }}</div>
                        </td>
                        <td style="width: 50%;">
                            <div class="info-row">Km: {{ number_format((int) ($document->vehicle?->current_km ?? 0), 0, ',', '.') }}</div>
                            <div class="info-row">Data emissão: {{ $document->issue_date?->format('d/m/Y') }}</div>
                            @if ($document->serviceOrder)
                                <div class="info-row">Ordem serviço: {{ $document->serviceOrder->number }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<!-- Linhas -->
<table class="lines">
    <thead>
        <tr>
            <th style="width: 4%;">#</th>
            <th style="width: 40%;">Descrição</th>
            <th style="width: 8%;">Un.</th>
            <th style="width: 8%;" class="right">Qtd.</th>
            <th style="width: 13%;" class="right">Preço unit.</th>
            <th style="width: 8%;" class="right">Desc.</th>
            <th style="width: 19%;" class="right">Total (c/ IVA)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($document->lines as $line)
            <tr>
                <td>{{ $line->line_number }}</td>
                <td>
                    {{ $line->description }}
                    <span class="muted">({{ $line->line_type->label() }}@if((float) $line->iva_rate > 0) · IVA {{ rtrim(rtrim(number_format($line->iva_rate * 100, 2, ',', '.'), '0'), ',') }}%@else · Isento @endif)</span>
                </td>
                <td>{{ $line->unit_code ?? 'UN' }}</td>
                <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2, ',', '.'), '0'), ',') }}</td>
                <td class="num">{{ number_format((float) $line->unit_price, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $line->discount_amount, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $line->total_line, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<!-- Totais + Impostos -->
<table style="margin-top: 8px;">
    <tr>
        <td style="width: 55%; vertical-align: top; padding-right: 8px;">
            <div class="info-title">Resumo de Impostos</div>
            <table class="taxes">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th class="right">Base tributável</th>
                        <th class="right">Taxa</th>
                        <th class="right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($document->taxes as $tax)
                        <tr>
                            <td>{{ $tax->description ?? $tax->tax_type }}@if($tax->exemption_code) ({{ $tax->exemption_code }})@endif</td>
                            <td class="right">{{ number_format((float) $tax->base_amount, 2, ',', '.') }}</td>
                            <td class="right">{{ rtrim(rtrim(number_format($tax->rate * 100, 2, ',', '.'), '0'), ',') }}%</td>
                            <td class="right">{{ number_format((float) $tax->amount, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="center muted">Sem impostos aplicáveis</td></tr>
                    @endforelse
                    @if ((float) $document->retention_total > 0)
                        <tr>
                            <td>Retenção na Fonte (6,5%)</td>
                            <td class="right">{{ number_format((float) $document->net_total - (float) $document->retention_total, 2, ',', '.') }}</td>
                            <td class="right">6,5%</td>
                            <td class="right">-{{ number_format((float) $document->retention_total, 2, ',', '.') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table class="totals">
                <tr><td>Subtotal (bruto)</td><td class="right">{{ number_format((float) $document->gross_total, 2, ',', '.') }} {{ $currency }}</td></tr>
                @if ((float) $document->discount_total > 0)
                    <tr><td>Desconto</td><td class="right">-{{ number_format((float) $document->discount_total, 2, ',', '.') }} {{ $currency }}</td></tr>
                @endif
                <tr><td>Base tributável</td><td class="right">{{ number_format((float) $document->net_total, 2, ',', '.') }} {{ $currency }}</td></tr>
                <tr><td>IVA</td><td class="right">{{ number_format((float) $document->iva_total, 2, ',', '.') }} {{ $currency }}</td></tr>
                <tr><td>Retenção na Fonte</td><td class="right">-{{ number_format((float) $document->retention_total, 2, ',', '.') }} {{ $currency }}</td></tr>
                <tr class="grand"><td>Total a pagar</td><td class="right">{{ number_format((float) $document->grand_total - (float) $document->retention_total, 2, ',', '.') }} {{ $currency }}</td></tr>
                <tr><td class="muted">Pago</td><td class="right muted">{{ number_format((float) $document->paid_total, 2, ',', '.') }} {{ $currency }}</td></tr>
                <tr><td class="muted">Saldo</td><td class="right muted">{{ number_format($document->remainingBalance(), 2, ',', '.') }} {{ $currency }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="words">Valor por extenso: {{ $amountInWords }}.</div>

<div class="info-block" style="margin-top: 8px;">
    <div class="info-title">Assinatura Digital / Certificação</div>
    <div class="info-row">Assinatura (4 caracteres do hash): <span class="bold">{{ $footerHash }}</span> | Nº certificação: <span class="bold">{{ $certificate }}</span></div>
    <div class="info-row">Hash: <span class="muted">{{ $document->hash ?? '—' }}</span></div>
    <div class="info-row">Emitido por: {{ $document->user?->name ?? 'Sistema' }} · Impresso em {{ $printDate }}</div>
</div>

<!-- Rodapé fiscal (fixo) -->
<div class="footer">
    {{ $footerText }}<br>
    Assinatura: <span class="sig">{{ $footerHash }}</span> · Certificação: {{ $certificate }} · autogest_nicola v{{ config('autogest.software.version') }}
</div>

</body>
</html>
