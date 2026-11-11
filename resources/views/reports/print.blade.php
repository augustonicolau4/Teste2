<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 14mm 12mm 18mm 12mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9px; color: #111827; margin: 0; }

        .header { border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 10px; }
        .header .name { font-size: 13px; font-weight: bold; }
        .muted { color: #6b7280; }
        .right { text-align: right; }

        table { width: 100%; border-collapse: collapse; }
        thead th { background: #111827; color: #fff; font-size: 8px; text-align: left; padding: 5px 6px; }
        tbody td { border-bottom: 1px solid #e5e7eb; padding: 4px 6px; }
        tfoot td { border-top: 2px solid #111827; padding: 4px 6px; font-weight: bold; }

        .totals-row td:first-child { text-align: right; text-transform: uppercase; font-size: 8px; color: #4b5563; }

        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; border-top: 1px solid #9ca3af; padding-top: 4px; font-size: 7px; color: #4b5563; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <table>
        <tr>
            <td style="width: 60%;">
                <div class="name">{{ config('autogest.software.name') }}</div>
                <div class="muted">{{ config('autogest.software.fullname') }}</div>
            </td>
            <td style="width: 40%;" class="right">
                <div style="font-size: 12px; font-weight: bold;">{{ $report->title }}</div>
                @if (isset($report->meta['from']))
                    <div class="muted">
                        Período:
                        {{ \Illuminate\Support\Carbon::parse($report->meta['from'])->format('d/m/Y') }}
                        a {{ \Illuminate\Support\Carbon::parse($report->meta['to'])->format('d/m/Y') }}
                    </div>
                @endif
                <div class="muted">Emitido em {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
</div>

<table>
    <thead>
        <tr>
            @foreach ($report->headings as $heading)
                <th>{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($report->rows as $row)
            <tr>
                @foreach ($row as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($report->headings) }}" style="text-align:center; color:#9ca3af; padding: 18px;">
                    Sem dados para o período selecionado.
                </td>
            </tr>
        @endforelse
    </tbody>
    @if (! empty($report->totals))
        <tfoot>
            @foreach ($report->totals as $label => $value)
                <tr class="totals-row">
                    <td colspan="{{ max(1, count($report->headings) - 1) }}">{{ $label }}</td>
                    <td class="right">{{ $value }}</td>
                </tr>
            @endforeach
        </tfoot>
    @endif
</table>

<div class="footer">
    {{ config('autogest.producer.signature') }} · Certificação {{ config('autogest.fiscal.software_certificate_number') }}
</div>

</body>
</html>
