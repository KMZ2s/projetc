<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->preventRequestForgery(except: [
            'checkout/callback',
        ]);

        $middleware->alias([
            'check.admin' => \App\Http\Middleware\CheckAdmin::class,
        ]);

        // Captura UTMs em qualquer rota web. Roda após o middleware de
        // session (que está no stack web por default), então pode ler/escrever
        // session direto.
        //
        // MaintenanceMode é appended depois pra ter session disponível
        // (precisa pra resolver auth() e checar se o usuário é admin).
        // Ele intercepta requests anônimos no front-end; admins logados,
        // webhooks, /admin e assets passam por bypasses internos.
        $middleware->web(append: [
            \App\Http\Middleware\CaptureUtmParameters::class,
            \App\Http\Middleware\MaintenanceMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();