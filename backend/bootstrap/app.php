<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all reverse proxies (Railway, Cloudflare, AWS load balancers) so HTTPS is properly detected
        $middleware->trustProxies(at: '*');

        // Register CORS globally so error responses & preflights always include CORS headers
        // Fix #7: SecurityHeaders adds HTTP security headers to every response
        $middleware->use([
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        // Alias for role-based access
        $middleware->alias([
            'role'         => \App\Http\Middleware\RoleMiddleware::class,
            'auth.session' => \App\Http\Middleware\AuthenticateSession::class,
            'tourist.auth' => \App\Http\Middleware\TouristAuthenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
