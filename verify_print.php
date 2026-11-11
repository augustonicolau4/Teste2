<?php

use App\Domain\Billing\Models\Document;
use App\Domain\Billing\Services\DocumentPdfRenderer;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$doc = Document::query()->latest('id')->first();

if (! $doc) {
    echo "SEM DOCUMENTO\n";
    exit(1);
}

echo "=== DOCUMENTO ===\n";
echo "Número: {$doc->number} | Tipo: {$doc->document_type->value}\n\n";

$renderer = app(DocumentPdfRenderer::class);

$outDir = storage_path('app/print_test');
@mkdir($outDir, 0775, true);

// --- A4 ---
$a4 = $renderer->a4($doc);
$a4Path = $outDir . '/doc_a4.pdf';
file_put_contents($a4Path, $a4->output());
echo 'A4 PDF: ' . strlen(file_get_contents($a4Path)) . " bytes -> {$a4Path}\n";

// --- Ticket 80mm ---
$t80 = $renderer->ticket($doc, ['format' => 'ticket', 'width_mm' => 80]);
$t80Path = $outDir . '/doc_ticket_80mm.pdf';
file_put_contents($t80Path, $t80->output());
echo 'Ticket 80mm PDF: ' . strlen(file_get_contents($t80Path)) . " bytes -> {$t80Path}\n";

// --- Ticket 58mm ---
$t58 = $renderer->ticket($doc, ['format' => 'ticket', 'width_mm' => 58]);
$t58Path = $outDir . '/doc_ticket_58mm.pdf';
file_put_contents($t58Path, $t58->output());
echo 'Ticket 58mm PDF: ' . strlen(file_get_contents($t58Path)) . " bytes -> {$t58Path}\n";

// --- HTML (ponte Node.js) ---
$htmlA4 = $renderer->html($doc, 'a4');
$htmlTk = $renderer->html($doc, 'ticket', ['format' => 'ticket']);
echo 'HTML A4: ' . strlen($htmlA4) . " bytes | contém rodapé NICOLA: " . (str_contains($htmlA4, 'NICOLA DA TEC') ? 'SIM' : 'NÃO') . "\n";
echo 'HTML Ticket: ' . strlen($htmlTk) . " bytes | contém QR: " . (str_contains($htmlTk, 'data:image/svg') ? 'SIM' : 'NÃO') . "\n";

echo "\nOK\n";
