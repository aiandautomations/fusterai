<?php

namespace Modules\CustomerPortal\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;

class EnsurePortalIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var Workspace $workspace */
        $workspace = $request->route('workspace');

        if (! ($workspace->settings['portal']['enabled'] ?? false)) {
            abort(404);
        }

        return $next($request);
    }
}
