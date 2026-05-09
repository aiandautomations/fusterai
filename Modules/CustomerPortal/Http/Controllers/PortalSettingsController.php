<?php

namespace Modules\CustomerPortal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage-settings');

        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $portal = $workspace->settings['portal'] ?? [];

        return Inertia::render('Settings/Portal', [
            'portal' => [
                'enabled' => $portal['enabled'] ?? false,
                'name' => $portal['name'] ?? $workspace->name.' Support',
                'welcome_text' => $portal['welcome_text'] ?? 'Submit and track your support requests.',
                'url' => url('/portal/'.$workspace->slug),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'enabled' => ['boolean'],
            'name' => ['nullable', 'string', 'max:100'],
            'welcome_text' => ['nullable', 'string', 'max:500'],
        ]);

        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $settings = $workspace->settings ?? [];
        $settings['portal'] = $validated;
        $workspace->settings = $settings;
        $workspace->save();

        return back()->with('success', 'Portal settings saved.');
    }
}
