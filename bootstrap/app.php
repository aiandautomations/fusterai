<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module.active' => \App\Http\Middleware\EnsureModuleIsActive::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\ConfigureAiSettings::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ── Scout/MeiliSearch: log but don't crash the request ────────────────
        $exceptions->render(function (\Meilisearch\Exceptions\CommunicationException $e, \Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::warning('MeiliSearch unavailable: ' . $e->getMessage());
            // Continue with the response — just skip the search index update
            return null;
        });

        // ── API: always return JSON errors ────────────────────────────────────
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Not found.'], 404);
            }
            // Web: render Inertia error page
            return \Inertia\Inertia::render('Error', ['status' => 404])
                ->toResponse($request)
                ->setStatusCode(404);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage() ?: 'HTTP error.'], $e->getStatusCode());
            }
            if (in_array($e->getStatusCode(), [403, 500, 503])) {
                return \Inertia\Inertia::render('Error', ['status' => $e->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($e->getStatusCode());
            }
        });

        // ── Throttle: friendly rate limit message ─────────────────────────────
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Too many requests. Please slow down.',
                ], 429)->withHeaders([
                    'Retry-After'           => $e->getHeaders()['Retry-After'] ?? 60,
                    'X-RateLimit-Limit'     => $e->getHeaders()['X-RateLimit-Limit'] ?? '',
                    'X-RateLimit-Remaining' => 0,
                ]);
            }
        });
    })->create();
