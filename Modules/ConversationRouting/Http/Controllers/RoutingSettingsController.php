<?php

namespace Modules\ConversationRouting\Http\Controllers;

use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\ConversationRouting\Models\RoutingConfig;

class RoutingSettingsController extends Controller
{
    public function index(Request $request)
    {
        $workspaceId = $request->user()->workspace_id;

        $mailboxes = Mailbox::where('workspace_id', $workspaceId)
            ->where('active', true)
            ->get(['id', 'name', 'email']);

        // Build per-mailbox config rows (plus workspace-level fallback)
        $configs = $mailboxes->map(function (Mailbox $mailbox) use ($workspaceId) {
            $config = RoutingConfig::where('workspace_id', $workspaceId)
                ->where('mailbox_id', $mailbox->id)
                ->first();

            $agentIds = User::where('workspace_id', $workspaceId)
                ->whereIn('role', ['agent', 'admin'])
                ->whereHas('mailboxes', fn ($q) => $q->where('mailbox_id', $mailbox->id))
                ->pluck('id');

            return [
                'mailbox_id'   => $mailbox->id,
                'mailbox_name' => $mailbox->name,
                'mailbox_email' => $mailbox->email,
                'mode'         => $config?->mode ?? 'round_robin',
                'active'       => $config?->active ?? false,
                'agent_count'  => $agentIds->count(),
            ];
        })->values()->all();

        return Inertia::render('Settings/Routing', [
            'configs' => $configs,
        ]);
    }

    public function update(Request $request)
    {
        $workspaceId = $request->user()->workspace_id;

        $validated = $request->validate([
            'configs'              => ['required', 'array'],
            'configs.*.mailbox_id' => ['required', 'integer', 'exists:mailboxes,id'],
            'configs.*.mode'       => ['required', 'in:round_robin,least_loaded'],
            'configs.*.active'     => ['required', 'boolean'],
        ]);

        foreach ($validated['configs'] as $row) {
            // Verify mailbox belongs to this workspace
            $mailbox = Mailbox::where('id', $row['mailbox_id'])
                ->where('workspace_id', $workspaceId)
                ->firstOrFail();

            RoutingConfig::updateOrCreate(
                ['workspace_id' => $workspaceId, 'mailbox_id' => $mailbox->id],
                [
                    'mode'   => $row['mode'],
                    'active' => $row['active'],
                ]
            );
        }

        return back()->with('success', 'Routing settings saved.');
    }
}
