<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica se o utilizador autenticado detém, pelo menos, um dos perfis indicados.
 *
 * Uso nas rotas: ->middleware('role:admin')  |  ->middleware('role:admin,gestor')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Autenticação necessária.');
        }

        if (! empty($roles) && ! $user->hasAnyRole($roles)) {
            abort(403, 'Não tem perfil autorizado para esta operação.');
        }

        return $next($request);
    }
}
