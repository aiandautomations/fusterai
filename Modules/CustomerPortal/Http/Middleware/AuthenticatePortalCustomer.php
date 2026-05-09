<?php

namespace Modules\CustomerPortal\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatePortalCustomer
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var Workspace $workspace */
        $workspace = $request->route('workspace');
        $customer = Auth::guard('customer_portal')->user();

        if (! $customer || $customer->workspace_id !== $workspace->id) {
            Auth::guard('customer_portal')->logout();

            return redirect()->route('portal.login', $workspace->slug)
                ->with('error', 'Please sign in to continue.');
        }

        return $next($request);
    }
}
