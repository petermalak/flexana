<?php

use App\Interfaces\Http\Middleware\EnsureFirebaseAuthenticated;
use Illuminate\Auth\AuthenticationException;
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
        // TrustProxies must run FIRST, before session handling
        // ServeStorageFiles runs early so /storage/* is served from disk (avoids 403 from server)
        $middleware->web(prepend: [
            \App\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\ServeStorageFiles::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'firebase.auth' => EnsureFirebaseAuthenticated::class,
            'api.signature' => \App\Http\Middleware\VerifyApiSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON 401 for unauthenticated API requests (expired/invalid tokens)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return null;
        });
    })->create();
