<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ResolveTenantWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');
        if ($trustedProxies !== null && $trustedProxies !== '') {
            $proxies = array_map('trim', explode(',', $trustedProxies));
            $middleware->trustProxies(at: count($proxies) === 1 && $proxies[0] === '*' ? '*' : $proxies);
        } elseif (env('APP_ENV') !== 'production') {
            // In local / development environment, allow wildcard for convenience
            $middleware->trustProxies(at: '*');
        }

        $middleware->web(append: [
            ResolveTenantWorkspace::class,
        ]);

        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
