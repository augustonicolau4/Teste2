<?php

namespace App\Domain\Notification\Services;

use App\Domain\Billing\Models\Document;
use App\Domain\CRM\Models\Vehicle;
use App\Domain\Notification\Models\AiConversation;
use App\Domain\Workshop\Models\ServiceOrder;
use Illuminate\Support\Str;

/**
 * Assistente de IA para consultas rápidas via WhatsApp.
 *
 * Responde a perguntas frequentes recorrendo aos dados da própria base
 * (estado da viatura, estado da OS, estado da fatura) e, em último recurso,
 * devolve um menu de ajuda. Cada interação é registada em `ai_conversations`.
 */
class AssistantService
{
    public function ask(string $message, string $from, array $context = []): string
    {
        $reply = $this->resolve($message, $context);

        $this->persist($from, $message, $reply, $context);

        return $reply;
    }

    /**
     * Indica se a mensagem justifica uma resposta automática (evita responder a
     * todas as mensagens). Reconhece comandos, matrículas, OS, documentos e saudações.
     */
    public function shouldRespond(string $message): bool
    {
        $text = Str::lower(trim($message));

        if ($text === '') {
            return false;
        }

        if (preg_match('/^[\/!]/', $text)) {
            return true;
        }

        if (preg_match('/\b[a-z]{2}-\d{2}-\d{2}-[a-z]{2}\b/i', $message)) {
            return true;
        }

        if (preg_match('/\bos[-\s]?\d+/i', $message)) {
            return true;
        }

        if (preg_match('/\b(FT|FR|FP|RC|NC|GR)[\s\/]?\d{4}/i', $message)) {
            return true;
        }

        return Str::contains($text, [
            'menu', 'ajuda', 'olá', 'ola', 'bom dia', 'boa tarde', 'boa noite',
            'horário', 'horario', 'contacto', 'telefone',
        ]);
    }

    private function resolve(string $message, array $context): string
    {
        $text = Str::lower(trim($message));

        if ($text === '') {
            return $this->menu();
        }

        // 1) Estado de viatura por matrícula (ex.: LD-45-67-AB)
        if (preg_match('/\b([a-z]{2}-\d{2}-\d{2}-[a-z]{2})\b/i', $message, $m)) {
            $vehicle = Vehicle::query()->where('plate', Str::upper($m[1]))->first();

            if ($vehicle) {
                return $this->vehicleStatus($vehicle);
            }

            return "Não encontrámos a viatura com a matrícula {$m[1]}. Confirme o formato (ex.: LD-00-00-AA).";
        }

        // 2) Estado da Ordem de Serviço (ex.: OS-1024 ou apenas o número)
        if (preg_match('/\bos[-\s]?(\d+)\b/i', $message, $m) || preg_match('/^\s*(\d{3,6})\s*$/', $message, $m)) {
            $order = ServiceOrder::query()
                ->where('number', 'like', '%' . ltrim($m[1], '0') . '%')
                ->latest('id')
                ->first();

            if ($order) {
                return sprintf(
                    'Ordem de serviço %s: estado atual "%s". Entrada em %s. Total estimado: %s Kz.',
                    $order->number,
                    $order->status->label(),
                    $order->entry_at?->format('d/m/Y') ?? '-',
                    number_format((float) $order->grand_total, 2, ',', '.'),
                );
            }

            return 'Não encontrámos nenhuma ordem de serviço com esse número. Verifique o número da OS.';
        }

        // 3) Estado de documento/fatura (ex.: FR 2026/00001)
        if (preg_match('/\b(FT|FR|FP|RC|NC|GR)[\s\/]?(\d{4})[\s\/]?(\d+)\b/i', $message, $m)) {
            $number = Str::upper($m[1]) . ' ' . $m[2] . '/' . str_pad($m[3], 5, '0', STR_PAD_LEFT);
            $document = Document::query()->where('number', $number)->first();

            if ($document) {
                return sprintf(
                    'Documento %s (%s) encontra-se "%s". Total: %s Kz | Saldo: %s Kz.',
                    $document->number,
                    $document->document_type->label(),
                    $document->status->label(),
                    number_format((float) $document->grand_total, 2, ',', '.'),
                    number_format($document->remainingBalance(), 2, ',', '.'),
                );
            }

            return 'Não localizámos esse documento. Confirme a série e o número (ex.: FR 2026/00001).';
        }

        // 4) Horário
        if (Str::contains($text, ['horário', 'horario', 'aberto', 'fecha', 'funciona'])) {
            return 'Horário de funcionamento: Segunda a Sexta, 08h00 às 17h00; Sábado, 08h00 às 13h00.';
        }

        // 5) Contactos
        if (Str::contains($text, ['contacto', 'telefone', 'email', 'e-mail', 'número'])) {
            $producer = config('autogest.producer');

            return sprintf('Contactos: %s | %s.', $producer['phone'], $producer['email']);
        }

        return $this->menu();
    }

    private function vehicleStatus(Vehicle $vehicle): string
    {
        $order = $vehicle->serviceOrders()->latest('id')->first();

        if (! $order) {
            return "Viatura {$vehicle->plate} sem ordens de serviço registadas.";
        }

        return sprintf(
            'Viatura %s: ordem de serviço %s no estado "%s". Previsão de conclusão: %s.',
            $vehicle->plate,
            $order->number,
            $order->status->label(),
            $order->promised_at?->format('d/m/Y') ?? 'a confirmar',
        );
    }

    private function menu(): string
    {
        return 'Olá! Bem-vindo à Oficina AutoGest Nicola. Pode perguntar: '
            . '1) Estado da viatura (ex.: LD-45-67-AB); '
            . '2) Estado da OS (ex.: OS-1024); '
            . '3) Estado de documento (ex.: FR 2026/00001); '
            . '4) Horário; 5) Contactos.';
    }

    private function persist(string $phone, string $message, string $reply, array $context): void
    {
        $sessionId = (string) ($context['session_id'] ?? $phone);

        AiConversation::query()->create([
            'session_id' => $sessionId,
            'phone'      => $phone,
            'role'       => 'user',
            'message'    => $message,
            'context'    => $context ?: null,
        ]);

        AiConversation::query()->create([
            'session_id' => $sessionId,
            'phone'      => $phone,
            'role'       => 'assistant',
            'message'    => $reply,
            'context'    => $context ?: null,
        ]);
    }
}
