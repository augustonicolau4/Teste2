<?php

use App\Http\Controllers\Api\V1\AssistantController;
use App\Http\Controllers\Api\V1\DocumentApiController;
use App\Http\Controllers\Api\V1\WhatsAppController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API autogest_nicola (v1)
|--------------------------------------------------------------------------
| Duas áreas distintas:
|   1. Integração Node.js (webhook/IA) — autenticada por X-Service-Token;
|   2. API do painel — autenticada por Sanctum (tokens).
*/

Route::prefix('v1')->group(function () {

    // 1) Microsserviço Node.js (Evolution API / impressão / IA)
    Route::middleware('service.token')->group(function () {
        Route::post('whatsapp/inbound', [WhatsAppController::class, 'inbound']);
        Route::post('whatsapp/status', [WhatsAppController::class, 'status']);
        Route::post('ai/ask', [AssistantController::class, 'ask']);
    });

    // 2) API do painel (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('documents', [DocumentApiController::class, 'index']);
        Route::get('documents/{document}', [DocumentApiController::class, 'show']);
        Route::get('documents/{document}/pdf/{format}', [DocumentApiController::class, 'pdf']);
    });
});
