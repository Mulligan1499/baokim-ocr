<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.apikey' => \App\Http\Middleware\AuthenticateApiKey::class,
        ]);

        // Trust all proxies — ngrok / Cloudflare tunnel terminate TLS ở edge và
        // forward HTTP xuống laptop với header X-Forwarded-Proto=https. Trust
        // proxies để Laravel sinh asset URL đúng scheme.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
