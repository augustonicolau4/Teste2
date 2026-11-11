<?php

namespace App\Domain\Workshop\Enums;

enum OrderStatus: string
{
    case RECEBIDA           = 'received';       // Viatura rececionada
    case DIAGNOSTICO        = 'diagnosis';      // Em diagnóstico
    case ORCAMENTO_ENVIADO  = 'quote_sent';     // Orçamento enviado ao cliente
    case ORCAMENTO_APROVADO = 'quote_approved'; // Orçamento aprovado
    case AGUARDA_PECAS      = 'waiting_parts';  // Aguarda peças
    case EM_EXECUCAO        = 'in_progress';    // Em execução
    case CONCLUIDA          = 'finished';       // Trabalho concluído
    case ENTREGUE           = 'delivered';      // Viatura entregue
    case CANCELADA          = 'cancelled';      // Cancelada

    public function label(): string
    {
        return match ($this) {
            self::RECEBIDA           => 'Rececionada',
            self::DIAGNOSTICO        => 'Em Diagnóstico',
            self::ORCAMENTO_ENVIADO  => 'Orçamento Enviado',
            self::ORCAMENTO_APROVADO => 'Orçamento Aprovado',
            self::AGUARDA_PECAS      => 'Aguarda Peças',
            self::EM_EXECUCAO        => 'Em Execução',
            self::CONCLUIDA          => 'Concluída',
            self::ENTREGUE           => 'Entregue',
            self::CANCELADA          => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RECEBIDA           => 'gray',
            self::DIAGNOSTICO        => 'blue',
            self::ORCAMENTO_ENVIADO  => 'amber',
            self::ORCAMENTO_APROVADO => 'indigo',
            self::AGUARDA_PECAS      => 'orange',
            self::EM_EXECUCAO        => 'cyan',
            self::CONCLUIDA          => 'green',
            self::ENTREGUE           => 'emerald',
            self::CANCELADA          => 'red',
        };
    }

    public function isBillable(): bool
    {
        return in_array($this, [self::CONCLUIDA, self::ENTREGUE], true);
    }

    /** Transições permitidas do fluxo de trabalho. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::RECEBIDA           => [self::DIAGNOSTICO, self::CANCELADA],
            self::DIAGNOSTICO        => [self::ORCAMENTO_ENVIADO, self::EM_EXECUCAO, self::CANCELADA],
            self::ORCAMENTO_ENVIADO  => [self::ORCAMENTO_APROVADO, self::CANCELADA],
            self::ORCAMENTO_APROVADO => [self::AGUARDA_PECAS, self::EM_EXECUCAO, self::CANCELADA],
            self::AGUARDA_PECAS      => [self::EM_EXECUCAO, self::CANCELADA],
            self::EM_EXECUCAO        => [self::AGUARDA_PECAS, self::CONCLUIDA, self::CANCELADA],
            self::CONCLUIDA          => [self::ENTREGUE],
            self::ENTREGUE           => [],
            self::CANCELADA          => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $s) => [$s->value => $s->label()]
        )->all();
    }
}
