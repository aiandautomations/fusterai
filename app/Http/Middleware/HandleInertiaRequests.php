<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
        $workspace = null;
        $getWorkspace = function () use ($request, &$workspace) {
            if ($workspace === null && $request->user()) {
                $workspace = \App\Models\Workspace::find($request->user()->workspace_id);
            }
            return $workspace;
        };

        return array_merge(parent::share($request), [
            'ziggy' => fn () => (new Ziggy)->toArray(),
            'auth'  => [
                'user' => $request->user() ? [
                    'id'           => $request->user()->id,
                    'name'         => $request->user()->name,
                    'email'        => $request->user()->email,
                    'role'         => $request->user()->role,
                    'avatar'       => $request->user()->avatar,
                    'workspace_id' => $request->user()->workspace_id,
                    'preferences'  => $request->user()->preferences,
                    'status'       => $request->user()->status ?? 'offline',
                ] : null,
            ],
            'agentStatuses' => fn () => $request->user()
                ? \App\Models\User::where('workspace_id', $request->user()->workspace_id)
                    ->get(['id', 'status'])
                    ->pluck('status', 'id')
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'token'   => fn () => $request->session()->get('token'),
            ],
            'notifications' => [
                'unread_count' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            ],
            'aiConfigured' => fn () => ! empty($getWorkspace()?->settings['ai_api_key']),
            'mailboxes' => fn () => $request->user()
                ? \App\Domains\Mailbox\Models\Mailbox::where('workspace_id', $request->user()->workspace_id)
                    ->get(['id', 'name'])
                : [],
            'tags' => fn () => $request->user()
                ? \App\Domains\Conversation\Models\Tag::where('workspace_id', $request->user()->workspace_id)
                    ->get(['id', 'name', 'color'])
                : [],
            'customViews' => fn () => $request->user()
                ? \App\Domains\Conversation\Models\CustomView::where('workspace_id', $request->user()->workspace_id)
                    ->where(function ($q) use ($request) {
                        $q->where('user_id', $request->user()->id)
                          ->orWhere('is_shared', true);
                    })
                    ->orderBy('order')
                    ->get(['id', 'name', 'color', 'filters', 'is_shared', 'user_id'])
                : [],
            'branding' => function () use ($getWorkspace) {
                $branding = $getWorkspace()?->settings['branding'] ?? [];
                return [
                    'name'     => $branding['name']     ?? null,
                    'logo_url' => $branding['logo_url'] ?? null,
                    'website'  => $branding['website']  ?? null,
                ];
            },
            'appearance' => function () use ($request, $getWorkspace) {
                if (! $request->user()) {
                    return ['mode' => 'system', 'color' => 'violet', 'font' => 'figtree', 'radius' => 'sm', 'contrast' => 'balanced'];
                }
                $appearance = $getWorkspace()?->settings['appearance'] ?? [];
                return [
                    'mode'     => $appearance['mode']     ?? 'system',
                    'color'    => $appearance['color']    ?? 'violet',
                    'font'     => $appearance['font']     ?? 'figtree',
                    'radius'   => $appearance['radius']   ?? 'sm',
                    'contrast' => $appearance['contrast'] ?? 'balanced',
                ];
            },
        ]);
    }
}
