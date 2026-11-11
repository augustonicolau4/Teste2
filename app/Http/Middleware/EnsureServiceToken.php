<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação entre serviços (Laravel <-> Node.js / Evolution API).
 * Valida o cabeçalho `X-Service-Token` contra o segredo partilhado.
 */
class EnsureServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('autogest.integrations.whatsapp.service_token');
        $provided = (string) $request->header('X-Service-Token');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Token de serviço inválido.');
        }

        return $next($request);
    }
}
