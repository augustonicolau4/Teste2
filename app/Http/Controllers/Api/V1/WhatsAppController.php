<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notification\Enums\MessageDirection;
use App\Domain\Notification\Enums\MessageStatus;
use App\Domain\Notification\Models\WhatsAppMessage;
use App\Domain\Notification\Services\AssistantService;
use App\Domain\Notification\Services\WhatsAppGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints consumidos pelo microsserviço Node.js (Evolution API).
 * Autenticação via middleware `service.token` (cabeçalho X-Service-Token).
 */
class WhatsAppController extends Controller
{
    /**
     * Mensagem recebida (Evolution -> Node -> Laravel).
     * Devolve opcionalmente uma `reply` que o Node.js reenvia ao cliente.
     */
    public function inbound(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'number'    => ['required', 'string', 'max:30'],
            'body'      => ['nullable', 'string', 'max:5000'],
            'messageId' => ['nullable', 'string', 'max:255'],
            'pushName'  => ['nullable', 'string', 'max:255'],
            'instance'  => ['nullable', 'string', 'max:255'],
            'raw'       => ['nullable', 'array'],
        ]);

        WhatsAppMessage::query()->create([
            'direction'    => MessageDirection::INBOUND->value,
            'phone'        => $data['number'],
            'message_type' => 'text',
            'message'      => $data['body'] ?? '',
            'status'       => MessageStatus::ENTREGUE->value,
            'external_id'  => $data['messageId'] ?? null,
            'payload'      => $data['raw'] ?? null,
            'delivered_at' => now(),
        ]);

        $body = (string) ($data['body'] ?? '');
        $reply = $assistant->shouldRespond($body)
            ? $assistant->ask($body, $data['number'], [
                'push_name' => $data['pushName'] ?? null,
                'instance'  => $data['instance'] ?? null,
            ])
            : null;

        return response()->json(['ok' => true, 'reply' => $reply]);
    }

    /**
     * Atualização do estado de entrega de uma mensagem enviada.
     */
    public function status(Request $request, WhatsAppGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'jobId'      => ['required', 'string', 'max:255'],
            'status'     => ['required', 'string', 'max:20'],
            'externalId' => ['nullable', 'string', 'max:255'],
        ]);

        $status = MessageStatus::tryFrom($data['status']);

        if (! $status) {
            return response()->json(['ok' => false, 'error' => 'invalid_status'], 422);
        }

        $message = $gateway->markStatus($data['jobId'], $status, $data['externalId'] ?? null);

        return response()->json(['ok' => (bool) $message]);
    }
}
