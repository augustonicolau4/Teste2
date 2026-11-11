<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureServiceToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'          => EnsureRole::class,
            'permission'    => EnsurePermission::class,
            'service.token' => EnsureServiceToken::class,
        ]);

        // Redirecionar convidados para o ecrã de autenticação.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Autenticação por sessão nas rotas de API usadas pelo painel (Sanctum stateful).
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
