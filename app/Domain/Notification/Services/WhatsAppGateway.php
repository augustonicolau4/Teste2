<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Enums\MessageDirection;
use App\Domain\Notification\Enums\MessageStatus;
use App\Domain\Notification\Models\PrintJob;
use App\Domain\Notification\Models\WhatsAppMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente HTTP para o microsserviço Node.js (whatsapp-service).
 *
 * Responsabilidades:
 *  - Envio de texto/documentos via Evolution API (WhatsApp);
 *  - Encaminhamento de trabalhos de impressão (Ticket/A4) para a ponte térmica;
 *  - Registo de cada operação nas tabelas `whatsapp_messages` e `print_jobs`.
 *
 * O serviço Node autentica os pedidos através do cabeçalho X-Service-Token.
 */
class WhatsAppGateway
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $token = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('autogest.integrations.whatsapp.service_url'), '/');
        $this->token = $token ?? (string) config('autogest.integrations.whatsapp.service_token');
    }

    /**
     * Envia uma mensagem de texto. Devolve o registo persistido.
     *
     * @param  array{company_id?:int|null,user_id?:int|null,related?:object|null,order_id?:string|int|null}  $meta
     */
    public function sendText(string $phone, string $message, array $meta = []): WhatsAppMessage
    {
        $record = $this->recordMessage($phone, 'text', $message, $meta);

        return $this->dispatch($record, '/send/text', [
            'number'  => $phone,
            'message' => $message,
            'orderId' => $meta['order_id'] ?? null,
        ]);
    }

    /**
     * Envia um documento (PDF) por WhatsApp, a partir de base64 ou de URL.
     *
     * @param  array<string, mixed>  $meta
     */
    public function sendDocument(
        string $phone,
        ?string $base64 = null,
        ?string $url = null,
        string $fileName = 'documento.pdf',
        string $caption = '',
        array $meta = [],
    ): WhatsAppMessage {
        $record = $this->recordMessage($phone, 'document', $caption ?: $fileName, $meta);
        $record->update(['media_path' => $fileName, 'media_caption' => $caption]);

        return $this->dispatch($record, '/send/document', [
            'number'         => $phone,
            'documentBase64' => $base64,
            'documentUrl'    => $url,
            'fileName'       => $fileName,
            'caption'        => $caption,
            'documentType'   => 'application/pdf',
            'orderId'        => $meta['order_id'] ?? null,
        ]);
    }

    /**
     * Encaminha um trabalho de impressão para a ponte térmica Node.js.
     *
     * @param  'ticket'|'a4'  $format
     * @param  array{company_id?:int|null,related?:object|null,printer?:string|null,copies?:int}  $meta
     */
    public function print(string $format, string $base64, string $fileName, array $meta = []): PrintJob
    {
        $job = PrintJob::query()->create([
            'company_id' => $meta['company_id'] ?? null,
            'printable_type' => isset($meta['related']) ? $meta['related']::class : null,
            'printable_id'   => $meta['related']?->getKey(),
            'format'    => $format,
            'printer'   => $meta['printer'] ?? config('autogest.printing.ticket.default_printer'),
            'status'    => 'queued',
            'copies'    => $meta['copies'] ?? 1,
        ]);

        try {
            $response = $this->client()->post("/print/{$format}", [
                'base64'   => $base64,
                'fileName' => $fileName,
            ]);

            $job->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'error'  => $response->successful() ? null : $response->body(),
            ]);
        } catch (Throwable $e) {
            $job->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $job->refresh();
    }

    /**
     * Atualiza o estado de entrega de uma mensagem (callback do serviço Node).
     */
    public function markStatus(string $jobId, MessageStatus $status, ?string $externalId = null): ?WhatsAppMessage
    {
        $record = WhatsAppMessage::query()->where('job_id', $jobId)->latest('id')->first();

        if (! $record) {
            return null;
        }

        $record->status = $status->value;
        $record->external_id ??= $externalId;

        if ($status === MessageStatus::ENVIADO) {
            $record->sent_at ??= now();
        } elseif ($status === MessageStatus::ENTREGUE) {
            $record->delivered_at ??= now();
        } elseif ($status === MessageStatus::LIDO) {
            $record->read_at ??= now();
        }

        $record->save();

        return $record;
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private function dispatch(WhatsAppMessage $record, string $path, array $payload): WhatsAppMessage
    {
        try {
            $response = $this->client()->post($path, $payload);

            if ($response->failed()) {
                return $this->markFailed($record, $response->body());
            }

            // O serviço responde 202 com { jobId, queued: true }.
            $record->update([
                'status' => MessageStatus::PENDENTE->value,
                'job_id' => $response->json('jobId'),
                'payload' => $payload,
            ]);
        } catch (Throwable $e) {
            return $this->markFailed($record, $e->getMessage());
        }

        return $record->refresh();
    }

    private function markFailed(WhatsAppMessage $record, string $error): WhatsAppMessage
    {
        $record->update([
            'status' => MessageStatus::FALHADO->value,
            'error'  => mb_substr($error, 0, 1000),
        ]);

        return $record->refresh();
    }

    /** @param array<string, mixed> $meta */
    private function recordMessage(string $phone, string $type, string $message, array $meta): WhatsAppMessage
    {
        $related = $meta['related'] ?? null;

        return WhatsAppMessage::query()->create([
            'company_id'   => $meta['company_id'] ?? null,
            'user_id'      => $meta['user_id'] ?? null,
            'related_type' => $related ? $related::class : null,
            'related_id'   => $related?->getKey(),
            'direction'    => MessageDirection::OUTBOUND->value,
            'phone'        => $phone,
            'message_type' => $type,
            'message'      => $message,
            'status'       => MessageStatus::PENDENTE->value,
        ]);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-Service-Token' => $this->token])
            ->timeout((int) config('autogest.integrations.whatsapp.timeout', 15))
            ->acceptJson();
    }
}
