<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notification\Services\AssistantService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Assistente de IA (consultas rápidas). Consumido pelo microsserviço Node.js.
 */
class AssistantController extends Controller
{
    public function ask(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'from'    => ['required', 'string', 'max:30'],
            'context' => ['nullable', 'array'],
        ]);

        $reply = $assistant->ask($data['message'], $data['from'], $data['context'] ?? []);

        return response()->json(['reply' => $reply]);
    }
}
