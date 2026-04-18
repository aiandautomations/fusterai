<?php

namespace Modules\ConversationRouting\Http\Controllers;

use App\Domains\Mailbox\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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

        $mailboxIds = $mailboxes->pluck('id');

        // Load all routing configs and agent counts in bulk (avoid N+1)
        $routingConfigs = RoutingConfig::where('workspace_id', $workspaceId)
            ->whereIn('mailbox_id', $mailboxIds)
            ->get()
            ->keyBy('mailbox_id');

        $agentCounts = DB::table('mailbox_user')
            ->join('users', 'users.id', '=', 'mailbox_user.user_id')
            ->where('users.workspace_id', $workspaceId)
            ->whereIn('users.role', ['agent', 'admin'])
            ->whereIn('mailbox_user.mailbox_id', $mailboxIds)
            ->selectRaw('mailbox_user.mailbox_id, COUNT(*) as agent_count')
            ->groupBy('mailbox_user.mailbox_id')
            ->pluck('agent_count', 'mailbox_user.mailbox_id');

        // Build per-mailbox config rows
        $configs = $mailboxes->map(function (Mailbox $mailbox) use ($routingConfigs, $agentCounts) {
            $config = $routingConfigs->get($mailbox->id);

            return [
                'mailbox_id' => $mailbox->id,
                'mailbox_name' => $mailbox->name,
                'mailbox_email' => $mailbox->email,
                'mode' => $config?->mode ?? 'round_robin',
                'active' => $config?->active ?? false,
                'agent_count' => (int) ($agentCounts->get($mailbox->id) ?? 0),
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
            'configs' => ['required', 'array'],
            'configs.*.mailbox_id' => ['required', 'integer', 'exists:mailboxes,id'],
            'configs.*.mode' => ['required', 'in:round_robin,least_loaded'],
            'configs.*.active' => ['required', 'boolean'],
        ]);

        foreach ($validated['configs'] as $row) {
            // Verify mailbox belongs to this workspace
            $mailbox = Mailbox::where('id', $row['mailbox_id'])
                ->where('workspace_id', $workspaceId)
                ->firstOrFail();

            RoutingConfig::updateOrCreate(
                ['workspace_id' => $workspaceId, 'mailbox_id' => $mailbox->id],
                [
                    'mode' => $row['mode'],
                    'active' => $row['active'],
                ]
            );
        }

        return back()->with('success', 'Routing settings saved.');
    }
}
