<?php

use App\Domain\Billing\Models\Document;
use App\Domain\Billing\Services\DigitalSignature;
use App\Domain\Billing\Services\HashChain;
use App\Domain\Saft\Services\SaftExporter;
use App\Domain\Saft\Services\SaftValidator;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$doc = Document::query()->with(['lines', 'taxes', 'customer', 'series'])->latest('id')->first();

if (! $doc) {
    echo "SEM DOCUMENTO\n";
    exit(1);
}

echo "=== DOCUMENTO ===\n";
echo "Número:        {$doc->number}\n";
echo "Tipo:          {$doc->document_type->value} ({$doc->document_type->label()})\n";
echo "Estado:        {$doc->status->value}\n";
echo "Base (net):    {$doc->net_total}\n";
echo "IVA (14%):     {$doc->iva_total}\n";
echo "Retenção:      {$doc->retention_total}\n";
echo "Total bruto:   {$doc->grand_total}\n";
echo "Pago:          {$doc->paid_total}\n";
echo "Saldo:         {$doc->balance}\n";
echo "Hash:          {$doc->hash}\n";
echo "HashControl:   {$doc->hash_control}\n";
echo "Rodapé (4ch):  {$doc->footerSignature()}\n";
echo "QR payload:    {$doc->qr_payload}\n";
echo "Linhas:        {$doc->lines->count()}\n";
foreach ($doc->lines as $l) {
    echo sprintf(
        "  - #%d %s | tipo=%s | qtd=%s x %s | base=%s | IVA=%s | ret=%s\n",
        $l->line_number, $l->description, $l->line_type->value, $l->quantity, $l->unit_price,
        $l->net_amount, $l->iva_amount, $l->retention_amount,
    );
}
echo "Impostos:      {$doc->taxes->count()}\n";

echo "\n=== ASSINATURA RSA ===\n";
$sig = app(DigitalSignature::class);
echo 'Chaves existem: ' . json_encode($sig->ensureKeysExist()) . "\n";
echo 'Assinatura válida: ' . ($sig->verify($doc->hash, $doc->signature) ? 'SIM' : 'NÃO') . "\n";

echo "\n=== CADEIA DE HASH ===\n";
$chain = app(HashChain::class);
$docs = Document::query()->where('is_fiscal', true)->orderBy('id')->get();
$check = $chain->verifySequence($docs);
echo 'Sequência válida: ' . ($check['valid'] ? 'SIM' : "NÃO (quebrada em {$check['broken_at']})") . "\n";
echo 'Documentos fiscais: ' . $docs->count() . "\n";

echo "\n=== SAF-T AO ===\n";
$from = now()->startOfYear()->toDateString();
$to = now()->toDateString();
$xml = app(SaftExporter::class)->xml($doc->company_id, $from, $to);
$path = storage_path('app/saft_test.xml');
file_put_contents($path, $xml);
echo 'XML gerado: ' . strlen($xml) . " bytes -> {$path}\n";

$result = app(SaftValidator::class)->validate($xml);
echo 'Válido: ' . ($result['valid'] ? 'SIM' : 'NÃO') . "\n";
echo 'XSD verificado: ' . ($result['schema_checked'] ? 'SIM' : 'não (XSD ausente)') . "\n";
foreach ($result['errors'] as $e) {
    echo "  ! {$e}\n";
}

echo "\nOK\n";
