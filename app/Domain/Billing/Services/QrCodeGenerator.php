<?php

namespace App\Domain\Billing\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Geração do QR Code impresso nos documentos (A4 e Ticket).
 *
 * É usado o formato SVG (renderização 100% PHP, sem dependência da extensão
 * Imagick), compatível com o barryvdh/laravel-dompdf via php-svg-lib.
 */
class QrCodeGenerator
{
    /**
     * QR Code como data-URI SVG, pronto a usar em <img src="...">.
     */
    public function dataUri(string $payload, ?int $size = null): string
    {
        $svg = $this->svg($payload, $size);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Marcação SVG do QR Code (pode ser inserida diretamente no Blade/PDF).
     */
    public function svg(string $payload, ?int $size = null): string
    {
        $size ??= (int) config('autogest.printing.qr.size', 110);

        return QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($payload);
    }

    /**
     * QR Code em PNG (requer a extensão Imagick).
     */
    public function png(string $payload, ?int $size = null): string
    {
        if (! extension_loaded('imagick')) {
            throw new \RuntimeException(
                'A geração de QR Code em PNG requer a extensão Imagick. Use o formato SVG (dataUri/svg).'
            );
        }

        $size ??= (int) config('autogest.printing.qr.size', 110);

        return QrCode::format('png')->size($size)->margin(1)->generate($payload);
    }
}
