<?php

namespace App\Domain\Notification\Enums;

enum MessageStatus: string
{
    case PENDENTE = 'pending';
    case ENVIADO  = 'sent';
    case ENTREGUE = 'delivered';
    case LIDO     = 'read';
    case FALHADO  = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE => 'Pendente',
            self::ENVIADO  => 'Enviado',
            self::ENTREGUE => 'Entregue',
            self::LIDO     => 'Lido',
            self::FALHADO  => 'Falhado',
        };
    }
}
