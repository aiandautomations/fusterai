<?php

namespace App\Http\Middleware;

use App\Services\AiSettingsService;
use Closure;
use Illuminate\Http\Request;

class ConfigureAiSettings
{
    public function __construct(private readonly AiSettingsService $aiSettings) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $user = $request->user()) {
            return $next($request);
        }

        // Wrap the entire request pipeline in withWorkspaceCredentials() so config
        // is always restored after the request completes — Octane-safe.
        return $this->aiSettings->withWorkspaceCredentials(
            $user->workspace_id,
            fn () => $next($request),
        );
    }
}
