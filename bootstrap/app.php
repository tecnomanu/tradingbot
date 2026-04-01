<?php

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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/telegram/webhook',
        ]);

        $middleware->alias([
            'api.key' => \App\Http\Middleware\ApiKeyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return consistent JSON for all /api/* routes
        $exceptions->shouldRenderJsonWhen(
            fn ($request, \Throwable $e) => $request->is('api/*')
        );

        // Render HTTP errors as Inertia pages for web routes
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson() && in_array($e->getStatusCode(), [404, 500, 503])) {
                try {
                    return \Inertia\Inertia::render('Error', ['status' => $e->getStatusCode()])
                        ->toResponse($request)
                        ->setStatusCode($e->getStatusCode());
                } catch (\Throwable) {
                    return null; // fall through to default handler
                }
            }
            return null;
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) {
                return null; // let default handler deal with it
            }

            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = match (true) {
                $status === 404 => 'Resource not found.',
                $status === 403 => 'Forbidden.',
                $status === 422 => 'Validation failed.',
                app()->isProduction() => 'Server error.',
                default => $e->getMessage(),
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $status === 422 && method_exists($e, 'errors') ? $e->errors() : null,
            ], $status ?: 500);
        });
    })->create();
