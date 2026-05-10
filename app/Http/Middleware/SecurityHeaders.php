<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // CSP: in dev mode, Vite serves assets from 127.0.0.1:5173 so we must
        // allow that origin explicitly. In production all assets are built into
        // public/build and served from 'self', so we can be strict.
        // blob: worker-src is required by Reverb's WebSocket client.
        if (app()->environment('local', 'development')) {
            // In dev, assets come from Vite (127.0.0.1:5173) and storage URLs are
            // generated from APP_URL which may differ from the artisan serve port.
            // Allow all localhost origins so nothing is blocked during development.
            $csp = implode('; ', [
                "default-src 'self' http://localhost:* http://127.0.0.1:*",
                "script-src 'self' http://localhost:* http://127.0.0.1:* 'unsafe-inline'",
                "style-src 'self' http://localhost:* http://127.0.0.1:* 'unsafe-inline'",
                "img-src 'self' http://localhost:* http://127.0.0.1:* data: blob: https:",
                "font-src 'self' http://localhost:* http://127.0.0.1:* data:",
                "connect-src 'self' http://localhost:* http://127.0.0.1:* ws: wss:",
                "worker-src 'self' blob:",
                "frame-ancestors 'none'",
            ]);
        } else {
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https:",
                "font-src 'self' data:",
                "connect-src 'self' ws: wss:",
                "worker-src 'self' blob:",
                "frame-ancestors 'none'",
            ]);
        }

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
