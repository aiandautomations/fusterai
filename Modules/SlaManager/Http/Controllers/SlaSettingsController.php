<?php

namespace Modules\SlaManager\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\SlaManager\Models\SlaPolicy;

class SlaSettingsController extends Controller
{
    public function index(Request $request)
    {
        $workspace = Workspace::find($request->user()->workspace_id);
        $defaults = SlaPolicy::defaults();

        $policies = collect(['urgent', 'high', 'normal', 'low'])->map(function ($priority) use ($workspace, $defaults) {
            $policy = SlaPolicy::where('workspace_id', $workspace->id)
                ->where('priority', $priority)
                ->first();

            return [
                'priority'               => $priority,
                'first_response_minutes' => $policy?->first_response_minutes ?? $defaults[$priority]['first_response_minutes'],
                'resolution_minutes'     => $policy?->resolution_minutes    ?? $defaults[$priority]['resolution_minutes'],
                'active'                 => $policy?->active ?? true,
            ];
        })->values()->all();

        return Inertia::render('Settings/Sla', [
            'policies' => $policies,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'policies'                             => ['required', 'array', 'size:4'],
            'policies.*.priority'                  => ['required', 'string', 'in:urgent,high,normal,low'],
            'policies.*.first_response_minutes'    => ['required', 'integer', 'min:1'],
            'policies.*.resolution_minutes'        => ['required', 'integer', 'min:1'],
            'policies.*.active'                    => ['boolean'],
        ]);

        $workspace = Workspace::find($request->user()->workspace_id);

        foreach ($validated['policies'] as $p) {
            SlaPolicy::updateOrCreate(
                ['workspace_id' => $workspace->id, 'priority' => $p['priority']],
                [
                    'name'                   => ucfirst($p['priority']) . ' Priority SLA',
                    'first_response_minutes' => $p['first_response_minutes'],
                    'resolution_minutes'     => $p['resolution_minutes'],
                    'active'                 => $p['active'] ?? true,
                ],
            );
        }

        return back()->with('success', 'SLA policies updated successfully.');
    }
}
