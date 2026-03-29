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
        if ($user = $request->user()) {
            $this->aiSettings->configureForWorkspace($user->workspace_id);
        }

        return $next($request);
    }
}
