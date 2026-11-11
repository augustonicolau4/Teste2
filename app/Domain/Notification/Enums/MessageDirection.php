<?php

namespace App\Domain\Notification\Enums;

/**
 * Direção da mensagem WhatsApp.
 */
enum MessageDirection: string
{
    case OUTBOUND = 'outbound';
    case INBOUND  = 'inbound';

    public function label(): string
    {
        return $this === self::OUTBOUND ? 'Saída' : 'Entrada';
    }
}
