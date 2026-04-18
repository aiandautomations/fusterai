<?php

use App\Http\Middleware\ConfigureAiSettings;
use App\Http\Middleware\EnsureModuleIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Meilisearch\Exceptions\CommunicationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            'module.active' => EnsureModuleIsActive::class,
        ]);

        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ConfigureAiSettings::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ── Scout/MeiliSearch: log but don't crash the request ────────────────
        $exceptions->render(function (CommunicationException $e, Request $request) {
            Log::warning('MeiliSearch unavailable: '.$e->getMessage());

            // Continue with the response — just skip the search index update
            return null;
        });

        // ── API: always return JSON errors ────────────────────────────────────
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            // Web: render Inertia error page
            return Inertia::render('Error', ['status' => 404])
                ->toResponse($request)
                ->setStatusCode(404);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage() ?: 'HTTP error.'], $e->getStatusCode());
            }
            if (in_array($e->getStatusCode(), [403, 500, 503])) {
                return Inertia::render('Error', ['status' => $e->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($e->getStatusCode());
            }
        });

        // ── Throttle: friendly rate limit message ─────────────────────────────
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Too many requests. Please slow down.',
                ], 429)->withHeaders([
                    'Retry-After' => $e->getHeaders()['Retry-After'] ?? 60,
                    'X-RateLimit-Limit' => $e->getHeaders()['X-RateLimit-Limit'] ?? '',
                    'X-RateLimit-Remaining' => 0,
                ]);
            }
        });
    })->create();
