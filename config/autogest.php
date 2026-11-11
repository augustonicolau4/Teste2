<?php

/*
|--------------------------------------------------------------------------
| autogest_nicola - Configuração do Software
|--------------------------------------------------------------------------
| Identidade do produtor, parâmetros fiscais (AGT), assinatura digital,
| séries de faturação e layout de impressão.
*/

return [

    'software' => [
        'name'     => 'autogest_nicola',
        'version'  => '1.0.0',
        'fullname' => 'autogest_nicola - Sistema de Gestão de Oficina e Faturação Certificada',
    ],

    /*
    | Dados do Produtor (obrigatórios nos documentos fiscais)
    */
    'producer' => [
        'name'       => 'NICOLA DA TEC - PRESTAÇÃO DE SERVIÇOS (SU), LDA.',
        'nif'        => '5002537540',
        'phone'      => '+244 938 033 192',
        'email'      => 'nicoladatec@gmail.com',
        'signature'  => 'Processado por programa validado - autogest_nicola | Desenvolvido por NICOLA DA TEC - PRESTAÇÃO DE SERVIÇOS (SU), LDA. (NIF: 5002537540)',
    ],

    /*
    | Parâmetros fiscais de Angola
    */
    'fiscal' => [
        'country'            => 'AO',
        'currency'           => 'AOA',
        'currency_symbol'    => 'Kz',
        'locale'             => 'pt_AO',
        'timezone'           => 'Africa/Luanda',

        // Taxas
        'iva_rate'           => 0.14,   // IVA Normal 14%
        'iva_reduced_rate'   => 0.07,   // IVA reduzida (7%)
        'iva_zero_rate'      => 0.00,   // Isento / 0%
        'retention_rate'     => 0.065,  // Retenção na Fonte 6,5% (serviços)

        // Arredondamento fiscal
        'round_decimals'     => 2,
        'round_mode'         => PHP_ROUND_HALF_UP,

        // IVA incluído no preço por omissão?
        'prices_include_iva' => false,

        // Retenção aplicada apenas a linhas de serviço/mão de obra
        'retention_applies_to' => ['service'],

        // Certificado do software junto da AGT
        'software_certificate_number' => 'AGT/FS/000000/2026',
        'agt_producer_registration'    => 'NIF 5002537540',
    ],

    /*
    | Séries de faturação por tipo de documento.
    | O formato suporta {SERIE}, {ANO}, {NUMERO}
    */
    'series' => [
        'FT' => ['prefix' => 'FT', 'format' => 'FT {ANO}/{NUMERO}', 'sequence_padding' => 5],
        'FR' => ['prefix' => 'FR', 'format' => 'FR {ANO}/{NUMERO}', 'sequence_padding' => 5],
        'FP' => ['prefix' => 'FP', 'format' => 'FP {ANO}/{NUMERO}', 'sequence_padding' => 5],
        'RC' => ['prefix' => 'RC', 'format' => 'RC {ANO}/{NUMERO}', 'sequence_padding' => 5],
        'NC' => ['prefix' => 'NC', 'format' => 'NC {ANO}/{NUMERO}', 'sequence_padding' => 5],
        'ND' => ['prefix' => 'ND', 'format' => 'ND {ANO}/{NUMERO}', 'sequence_padding' => 5],
        'GR' => ['prefix' => 'GR', 'format' => 'GR {ANO}/{NUMERO}', 'sequence_padding' => 5],
    ],

    /*
    | Assinatura digital assimétrica (RSA / OpenSSL) encadeada por hash.
    */
    'signature' => [
        'algorithm'       => OPENSSL_ALGO_SHA256,
        'private_key_path' => storage_path('app/keys/private.pem'),
        'public_key_path'  => storage_path('app/keys/public.pem'),
        'passphrase'      => env('AUTOGEST_KEY_PASSPHRASE', ''),
        // Fonte de entropia para o "4 caracteres do hash"
        'hash_footer_length' => 4,
    ],

    /*
    | SAF-T AO (Standard Audit File for Tax purposes)
    */
    'saft' => [
        'version'          => '1.01_01',
        'tax_country'      => 'AO',
        'schema_path'      => resource_path('saft/saft_ao_1.01_01.xsd'),
        'export_path'      => 'saft',
        'product_id'       => 'autogest_nicola/1.0.0',
        'product_version'  => '1.0.0',
    ],

    /*
    | Impressão (A4 e Ticket Térmico 80mm/58mm)
    */
    'printing' => [
        'a4' => [
            'paper'    => 'a4',
            'dpi'      => 96,
            'logo'     => 'images/logo.png',
            'footer'   => 'Processado por programa validado - autogest_nicola | Desenvolvido por NICOLA DA TEC - PRESTAÇÃO DE SERVIÇOS (SU), LDA. (NIF: 5002537540)',
        ],
        'ticket' => [
            'width_mm'  => 80,
            'default_printer' => env('AUTOGEST_TICKET_PRINTER', 'EPSON-TM-T20III'),
            'node_service'    => env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3100'),
            'footer'    => 'Processado por programa validado - autogest_nicola | NICOLA DA TEC (SU), LDA. NIF 5002537540',
        ],
        'qr' => [
            'enabled' => true,
            'size'    => 110,
            'payload' => 'document_hash', // document_hash | nif+total+number | url
        ],
    ],

    /*
    | Integração Node.js (WhatsApp / IA / Ponte térmica)
    */
    'integrations' => [
        'whatsapp' => [
            'service_url'    => env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3100'),
            'service_token'  => env('WHATSAPP_SERVICE_TOKEN', 'change-me'),
            'country_code'   => '244',
            'timeout'        => 15,
        ],
        'ai' => [
            'enabled'        => env('AUTOGEST_AI_ENABLED', true),
            'provider'       => env('AUTOGEST_AI_PROVIDER', 'local'),
            'api_key'        => env('AUTOGEST_AI_API_KEY', ''),
            'endpoint'       => env('AUTOGEST_AI_ENDPOINT', ''),
            'model'          => env('AUTOGEST_AI_MODEL', 'gpt-4o-mini'),
        ],
    ],

    /*
    | Encargos laborais (Angola)
    */
    'hr' => [
        'inss_employee_rate' => 0.03,  // Trabalhador 3%
        'inss_employer_rate' => 0.08,  // Entidade empregadora 8%
        'irt_brackets'       => [
            // [limite_superior, taxa_marginal, parcela_fixa]
            [100_000.0,  0.00, 0.0],      // até 100.000 Kz - isento
            [150_000.0,  0.13, 0.0],
            [200_000.0,  0.16, 6_500.0],
            [300_000.0,  0.18, 10_500.0],
            [500_000.0,  0.19, 22_500.0],
            [1_000_000.0, 0.20, 52_500.0],
            [1_500_000.0, 0.21, 82_500.0],
            [2_000_000.0, 0.22, 112_500.0],
            [2_500_000.0, 0.23, 142_500.0],
            [PHP_FLOAT_MAX, 0.24, 172_500.0],
        ],
        'subsidio_alimentacao_isencao' => 30_000.0,
    ],

    /*
    | Comissões por omissão
    */
    'commissions' => [
        'technician_default_rate' => 0.05,   // 5% sobre mão de obra executada
        'promoter_default_rate'   => 0.03,   // 3% sobre valor angariado
    ],
];
