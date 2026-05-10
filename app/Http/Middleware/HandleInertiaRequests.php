<?php

namespace App\Http\Middleware;

use App\Domains\Conversation\Models\CustomView;
use App\Domains\Conversation\Models\Tag;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        // Disable version checking in local/dev — avoids 409s after npm builds
        if (app()->environment('local')) {
            return null;
        }

        return parent::version($request);
    }

    public function share(Request $request): array
    {
        // Load the workspace once and memoize across all closures below.
        // Avoids redundant DB queries when branding, appearance, and aiConfigured
        // all need the same workspace row.
        // Always resolve via the web guard — portal customers use customer_portal guard
        // and must not bleed into agent-specific shared data.
        $agentUser = $request->user('web');

        $workspace = null;
        $getWorkspace = function () use ($agentUser, &$workspace) {
            if ($workspace === null && $agentUser) {
                $workspace = Workspace::find($agentUser->workspace_id);
            }

            return $workspace;
        };

        return array_merge(parent::share($request), [
            'ziggy' => fn () => (new Ziggy)->toArray(),
            'auth' => [
                'user' => $agentUser ? [
                    'id' => $agentUser->id,
                    'name' => $agentUser->name,
                    'email' => $agentUser->email,
                    'role' => $agentUser->role,
                    'avatar' => $agentUser->avatar,
                    'workspace_id' => $agentUser->workspace_id,
                    'preferences' => $agentUser->preferences,
                    'status' => $agentUser->status ?? 'offline',
                ] : null,
            ],
            'agentStatuses' => fn () => $agentUser
                ? Cache::remember(
                    "workspace.agent_statuses.{$agentUser->workspace_id}",
                    now()->addSeconds(30),
                    fn () => User::where('workspace_id', $agentUser->workspace_id)
                        ->get(['id', 'status'])
                        ->pluck('status', 'id'),
                )
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'token' => fn () => $request->session()->get('token'),
            ],
            'notifications' => [
                'unread_count' => fn () => $agentUser?->unreadNotifications()->count() ?? 0,
            ],
            'aiConfigured' => fn () => ! empty($getWorkspace()?->settings['ai_api_key']),
            'mailboxes' => fn () => $agentUser
                ? Mailbox::where('workspace_id', $agentUser->workspace_id)
                    ->get(['id', 'name'])
                : [],
            'tags' => fn () => $agentUser
                ? Tag::where('workspace_id', $agentUser->workspace_id)
                    ->get(['id', 'name', 'color'])
                : [],
            'customViews' => fn () => $agentUser
                ? CustomView::where('workspace_id', $agentUser->workspace_id)
                    ->where(function ($q) use ($agentUser) {
                        $q->where('user_id', $agentUser->id)
                            ->orWhere('is_shared', true);
                    })
                    ->orderBy('order')
                    ->get(['id', 'name', 'color', 'filters', 'is_shared', 'user_id'])
                : [],
            'branding' => function () use ($getWorkspace) {
                $branding = $getWorkspace()?->settings['branding'] ?? [];

                return [
                    'name' => $branding['name'] ?? null,
                    'logo_url' => $branding['logo_url'] ?? null,
                    'website' => $branding['website'] ?? null,
                ];
            },
            'appearance' => function () use ($agentUser, $getWorkspace) {
                if (! $agentUser) {
                    return ['mode' => 'system', 'color' => 'violet', 'font' => 'figtree', 'radius' => 'sm', 'contrast' => 'balanced'];
                }
                $appearance = $getWorkspace()?->settings['appearance'] ?? [];

                return [
                    'mode' => $appearance['mode'] ?? 'system',
                    'color' => $appearance['color'] ?? 'violet',
                    'font' => $appearance['font'] ?? 'figtree',
                    'radius' => $appearance['radius'] ?? 'sm',
                    'contrast' => $appearance['contrast'] ?? 'balanced',
                ];
            },
        ]);
    }
}
