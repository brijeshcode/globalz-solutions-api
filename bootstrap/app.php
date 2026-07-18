<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Exceptions\NoCurrentTenant;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Landlord routes - NO tenant middleware
            Route::prefix('api/landlord')
                ->middleware([
                    'api',
                    'auth:sanctum',
                ])
                ->withoutMiddleware([
                    \Spatie\Multitenancy\Http\Middleware\NeedsTenant::class,
                    \Spatie\Multitenancy\Http\Middleware\EnsureValidTenantSession::class,
                ])
                ->name('landlord.')
                ->group(base_path('routes/landlord.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'feature' => \App\Http\Middleware\RequireFeature::class,
            'bug-lock' => \App\Http\Middleware\BugLockMiddleware::class,
            'module.lock' => \App\Http\Middleware\EnforceModuleLock::class,
        ]);
        // Add CORS and session middleware for API (needed for CSRF and tenant session validation)
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        // Add tenant middleware to all API routes
        $middleware->api(append: [
            \App\Http\Middleware\AttachCacheVersion::class,
            \App\Http\Middleware\LogApiHits::class,
            \Spatie\Multitenancy\Http\Middleware\NeedsTenant::class,
            \Spatie\Multitenancy\Http\Middleware\EnsureValidTenantSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return clean JSON response instead of stack trace (reason already logged in OriginTenantFinder)
        $exceptions->renderable(function (NoCurrentTenant $e, $request) {
            return response()->json([
                'message' => 'Unable to identify system. Please check your domain configuration.',
            ], 403);
        });

    })->create();
