<?php

namespace Modules\CustomerPortal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CustomerPortal\Http\Requests\SendMagicLinkRequest;
use Modules\CustomerPortal\Services\PortalAuthService;

class PortalAuthController extends Controller
{
    public function __construct(private PortalAuthService $service) {}

    public function show(Workspace $workspace): Response|RedirectResponse
    {
        $customer = Auth::guard('customer_portal')->user();

        if ($customer && $customer->workspace_id === $workspace->id) {
            return redirect()->route('portal.tickets.index', $workspace->slug);
        }

        return Inertia::render('Portal/Login', [
            'workspace' => $this->workspaceProps($workspace),
            'status' => session('error'),
        ]);
    }

    public function sendLink(SendMagicLinkRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->service->sendMagicLink($workspace, $request->validated('email'));

        return redirect()->route('portal.check-email', $workspace->slug);
    }

    public function checkEmail(Workspace $workspace): Response
    {
        return Inertia::render('Portal/CheckEmail', [
            'workspace' => $this->workspaceProps($workspace),
        ]);
    }

    public function authenticate(Request $request, Workspace $workspace, string $token): RedirectResponse
    {
        $customer = $this->service->verify($workspace, $token);

        if (! $customer) {
            return redirect()->route('portal.login', $workspace->slug)
                ->with('error', 'This link has expired or is invalid. Please request a new one.');
        }

        Auth::guard('customer_portal')->login($customer, remember: true);
        $request->session()->regenerate();

        return redirect()->route('portal.tickets.index', $workspace->slug);
    }

    public function logout(Request $request, Workspace $workspace): RedirectResponse
    {
        Auth::guard('customer_portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login', $workspace->slug);
    }

    private function workspaceProps(Workspace $workspace): array
    {
        $portal = $workspace->settings['portal'] ?? [];
        $branding = $workspace->settings['branding'] ?? [];

        return [
            'name' => $portal['name'] ?? $workspace->name.' Support',
            'slug' => $workspace->slug,
            'welcome_text' => $portal['welcome_text'] ?? 'Submit and track your support requests.',
            'logo_url' => $branding['logo_url'] ?? null,
        ];
    }
}
