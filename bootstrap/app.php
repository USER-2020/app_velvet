<?php

use App\Http\Middleware\EnsureActiveOperationalProfile;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ngrok termina la conexión HTTPS y reenvía la petición al servidor local.
        // Confiar en sus encabezados permite que Laravel genere URLs HTTPS.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));

        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\ThrottleRequests::class, \App\Http\Middleware\Agent\AgentTransport::class);
        $middleware->prependToPriorityList(\App\Http\Middleware\PreserveAgentOAuthIntent::class, \Illuminate\Auth\Middleware\Authenticate::class);

        $middleware->trimStrings(except: [fn ($request) => $request->is('api/agent/*')]);
        $middleware->convertEmptyStringsToNull(except: [fn ($request) => $request->is('api/agent/*')]);

        $middleware->web(append: [
            EnsureActiveUser::class,
            AddSecurityHeaders::class,
            \App\Http\Middleware\PreserveAgentOAuthIntent::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active.profile' => EnsureActiveOperationalProfile::class,
        ]);

        // Didit calls this endpoint server-to-server, without a Laravel CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/didit']);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
