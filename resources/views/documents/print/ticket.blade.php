<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="utf-8">
    <title>{{ $document->number }} - Ticket</title>
    <style>
        @page { margin: 3mm 3mm 4mm 3mm; }

        * { font-family: "DejaVu Sans Mono", "DejaVu Sans", monospace; }
        body { font-size: 8.5px; color: #000; margin: 0; line-height: 1.35; }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #444; }
        .dashed { border-top: 1px dashed #000; margin: 4px 0; }
        .solid { border-top: 1px solid #000; margin: 4px 0; }

        /* Cabeçalho */
        .company-name { font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .company-meta { font-size: 7.5px; }

        /* Bloco do documento */
        .doc-type { font-size: 12px; font-weight: bold; letter-spacing: 0.5px; margin-top: 3px; }
        .doc-number { font-size: 10px; font-weight: bold; }
        .doc-status { font-size: 7.5px; }

        .nonfiscal {
            border: 1px solid #000; padding: 2px; margin: 3px 0;
            font-size: 8px; font-weight: bold; text-align: center;
        }

        table { width: 100%; border-collapse: collapse; }

        /* Blocos de dados */
        .info-title { font-size: 7px; font-weight: bold; text-transform: uppercase; }

        /* Tabela de artigos */
        .lines th { font-size: 7px; font-weight: bold; text-align: left; border-bottom: 1px solid #000; padding: 1px 0; }
        .lines td { font-size: 8px; padding: 1px 0; vertical-align: top; }
        .lines td.num { text-align: right; white-space: nowrap; }

        /* Totais */
        .totals td { font-size: 8.5px; padding: 0.5px 0; }
        .totals .grand { font-size: 10px; font-weight: bold; border-top: 1px solid #000; padding-top: 2px; }

        .taxes td { font-size: 7.5px; padding: 0.5px 0; }

        .words { font-size: 7.5px; font-style: italic; margin-top: 3px; }

        .sig-box { font-size: 7.5px; margin-top: 4px; }

        .qr { margin: 4px auto 0 auto; }

        .footer { font-size: 6.5px; text-align: center; color: #222; margin-top: 5px; }
    </style>
</head>
<body>

<!-- Cabeçalho da empresa -->
<div class="center">
    @if ($logoDataUri)
        <div><img src="{{ $logoDataUri }}" style="max-height: 40px;" alt="logotipo"></div>
    @endif
    <div class="company-name">{{ $company->name }}</div>
    <div class="company-meta">NIF: {{ $company->nif }}</div>
    <div class="company-meta">{{ $company->address }}@if($company->city), {{ $company->city }}@endif</div>
    <div class="company-meta">
        @if ($company->phone) Tel: {{ $company->phone }}@endif
        @if ($company->email) | {{ $company->email }}@endif
    </div>
</div>

<div class="dashed"></div>

<!-- Tipo e número do documento -->
<div class="center">
    <div class="doc-type">{{ $document->document_type->label() }}</div>
    <div class="doc-number">{{ $document->number }}</div>
    <div class="doc-status muted">{{ $document->status->label() }} · {{ $document->issue_date?->format('d/m/Y H:i') }}</div>
</div>

@unless ($document->is_fiscal)
    <div class="nonfiscal">DOCUMENTO NÃO FISCAL - SEM VALOR DE FATURA</div>
@endunless

<div class="dashed"></div>

<!-- Cliente -->
<div>
    <div class="info-title">Cliente</div>
    <div class="bold">{{ $document->customer?->name ?? 'Consumidor Final' }}</div>
    @if ($document->customer?->nif) <div>NIF: {{ $document->customer->nif }}</div> @endif
    @if ($document->customer?->phone) <div>Tel: {{ $document->customer->phone }}</div> @endif
</div>

<!-- Viatura / Operação -->
@if ($document->vehicle || $document->serviceOrder)
    <div class="dashed"></div>
    <div>
        <div class="info-title">Viatura / Operação</div>
        @if ($document->vehicle)
            <div>Matrícula: <span class="bold">{{ $document->vehicle->plate ?? 'N/D' }}</span>
                @if ($document->vehicle->brand || $document->vehicle->model)
                    · {{ $document->vehicle->brand }} {{ $document->vehicle->model }}
                @endif
            </div>
            @if ($document->vehicle->vin) <div>VIN: {{ $document->vehicle->vin }}</div> @endif
            <div>Km: {{ number_format((int) ($document->vehicle->current_km ?? 0), 0, ',', '.') }}</div>
        @endif
        @if ($document->serviceOrder)
            <div>Ordem de serviço: <span class="bold">{{ $document->serviceOrder->number }}</span></div>
        @endif
    </div>
@endif

<div class="dashed"></div>

<!-- Artigos -->
<table class="lines">
    <thead>
        <tr>
            <th style="width: 52%;">Descrição</th>
            <th style="width: 16%;" class="right">Qtd</th>
            <th style="width: 16%;" class="right">P.Unit</th>
            <th style="width: 16%;" class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($document->lines as $line)
            <tr>
                <td>
                    {{ $line->description }}
                    <span class="muted">({{ $line->line_type->label() }}@if((float) $line->iva_rate > 0) · IVA {{ rtrim(rtrim(number_format($line->iva_rate * 100, 2, ',', '.'), '0'), ',') }}%@endif)</span>
                </td>
                <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2, ',', '.'), '0'), ',') }} {{ $line->unit_code ?? 'UN' }}</td>
                <td class="num">{{ number_format((float) $line->unit_price, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $line->total_line, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="solid"></div>

<!-- Totais -->
<table class="totals">
    <tr><td>Subtotal (bruto)</td><td class="right">{{ number_format((float) $document->gross_total, 2, ',', '.') }} {{ $currency }}</td></tr>
    @if ((float) $document->discount_total > 0)
        <tr><td>Desconto</td><td class="right">-{{ number_format((float) $document->discount_total, 2, ',', '.') }} {{ $currency }}</td></tr>
    @endif
    <tr><td>Base tributável</td><td class="right">{{ number_format((float) $document->net_total, 2, ',', '.') }} {{ $currency }}</td></tr>
    <tr><td>IVA</td><td class="right">{{ number_format((float) $document->iva_total, 2, ',', '.') }} {{ $currency }}</td></tr>
    @if ((float) $document->retention_total > 0)
        <tr><td>Retenção na Fonte (6,5%)</td><td class="right">-{{ number_format((float) $document->retention_total, 2, ',', '.') }} {{ $currency }}</td></tr>
    @endif
    <tr class="grand"><td>TOTAL A PAGAR</td><td class="right">{{ number_format((float) $document->grand_total - (float) $document->retention_total, 2, ',', '.') }} {{ $currency }}</td></tr>
    <tr><td class="muted">Pago</td><td class="right muted">{{ number_format((float) $document->paid_total, 2, ',', '.') }} {{ $currency }}</td></tr>
    <tr><td class="muted">Saldo</td><td class="right muted">{{ number_format($document->remainingBalance(), 2, ',', '.') }} {{ $currency }}</td></tr>
</table>

<!-- Resumo de impostos (compacto) -->
@if ($document->taxes->isNotEmpty())
    <div class="dashed"></div>
    <table class="taxes">
        <thead>
            <tr>
                <td class="bold">Imposto</td>
                <td class="right bold">Base</td>
                <td class="right bold">Taxa</td>
                <td class="right bold">Valor</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($document->taxes as $tax)
                <tr>
                    <td>{{ $tax->description ?? $tax->tax_type }}@if($tax->exemption_code) ({{ $tax->exemption_code }})@endif</td>
                    <td class="right">{{ number_format((float) $tax->base_amount, 2, ',', '.') }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format($tax->rate * 100, 2, ',', '.'), '0'), ',') }}%</td>
                    <td class="right">{{ number_format((float) $tax->amount, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="dashed"></div>

<div class="words">Valor por extenso: {{ $amountInWords }}.</div>

<!-- Assinatura digital / certificação -->
<div class="sig-box">
    <div>Assinatura (4 car. do hash): <span class="bold">{{ $footerHash }}</span></div>
    <div>Nº certificação: {{ $certificate }}</div>
    <div class="muted">Hash: {{ $document->hash ?? '—' }}</div>
    <div class="muted">Operador: {{ $document->user?->name ?? 'Sistema' }} · {{ $printDate }}</div>
</div>

<!-- QR Code -->
@if ($qrDataUri)
    <div class="center qr">
        <img src="{{ $qrDataUri }}" width="120" height="120" alt="QR">
    </div>
@endif

<div class="dashed"></div>

<!-- Rodapé fiscal -->
<div class="footer">
    {{ $footerText }}<br>
    Assinatura: {{ $footerHash }} · Certificação: {{ $certificate }} · autogest_nicola v{{ config('autogest.software.version') }}
    @if ($document->printed_count) · Via {{ $document->printed_count }} @endif
</div>

</body>
</html>
