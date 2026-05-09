<?php

namespace Modules\SatisfactionSurvey\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SurveySettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage-settings');

        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $survey = $workspace->settings['survey'] ?? [];

        return Inertia::render('Settings/Survey', [
            'survey' => [
                'enabled' => $survey['enabled'] ?? true,
                'delay_minutes' => $survey['delay_minutes'] ?? 5,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'delay_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);

        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $settings = $workspace->settings ?? [];
        $settings['survey'] = $validated;
        $workspace->settings = $settings;
        $workspace->save();

        return back()->with('success', 'Survey settings saved.');
    }
}
