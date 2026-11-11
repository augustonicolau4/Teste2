<?php

namespace App\Domain\Saft\Services;

use DOMDocument;
use Illuminate\Support\Facades\File;

/**
 * Validação estrutural do SAF-T AO (XSD, se disponível) e verificação básica de integridade.
 */
class SaftValidator
{
    /**
     * @return array{valid: bool, errors: array<int, string>, schema_checked: bool}
     */
    public function validate(string $xml): array
    {
        $errors = [];

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $schemaPath = config('autogest.saft.schema_path');
        $schemaChecked = false;

        if ($schemaPath && File::exists($schemaPath)) {
            libxml_use_internal_errors(true);
            $schemaChecked = true;

            if (! $dom->schemaValidate($schemaPath)) {
                foreach (libxml_get_errors() as $error) {
                    $errors[] = trim($error->message) . " (linha {$error->line})";
                }
                libxml_clear_errors();
            }
        }

        // Verificações mínimas de conteúdo
        foreach (['Header', 'MasterFiles', 'SourceDocuments'] as $node) {
            if (! $dom->getElementsByTagName($node)->length) {
                $errors[] = "Elemento obrigatório em falta: {$node}.";
            }
        }

        return [
            'valid'          => $errors === [],
            'errors'         => $errors,
            'schema_checked' => $schemaChecked,
        ];
    }
}
