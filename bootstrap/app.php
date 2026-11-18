<?php

use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            SetLocale::class,
            SecurityHeadersMiddleware::class,
            IdentifyTenant::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'stripe/*',
            'demo-login*',
        ]);
        $middleware->alias([
            'superadmin' => EnsureSuperadmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
