<?php

namespace App\Http\Middleware;

use App\Domains\AI\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abort with 404 if the named module is not active.
 *
 * Usage in routes:  Route::middleware(['auth', 'module.active:SlaManager'])
 *
 * The result is cached for 5 minutes so the DB is not hit on every request.
 * Cache is flushed when the module is toggled in SettingsController.
 */
class EnsureModuleIsActive
{
    public function handle(Request $request, Closure $next, string $alias): Response
    {
        $isActive = Cache::remember(
            "module.active.{$alias}",
            now()->addMinutes(5),
            fn () => Module::where('alias', $alias)->where('active', true)->exists()
        );

        if (! $isActive) {
            abort(404);
        }

        return $next($request);
    }
}
