<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica permissões granulares (spatie/laravel-permission).
 *
 * Uso nas rotas: ->middleware('permission:documents.issue')
 *                ->middleware('permission:documents.issue,documents.cancel')
 *
 * O perfil Admin tem acesso total (bypass).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Autenticação necessária.');
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        if (! empty($permissions) && ! $user->hasAnyPermission($permissions)) {
            abort(403, 'Não tem permissão para esta operação.');
        }

        return $next($request);
    }
}
