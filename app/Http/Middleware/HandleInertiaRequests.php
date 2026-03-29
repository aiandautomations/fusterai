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
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'notifications' => [
                'unread_count' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            ],
            'mailboxes' => fn () => $request->user()
                ? \App\Domains\Mailbox\Models\Mailbox::where('workspace_id', $request->user()->workspace_id)
                    ->get(['id', 'name'])
                : [],
            'tags' => fn () => $request->user()
                ? \App\Domains\Conversation\Models\Tag::where('workspace_id', $request->user()->workspace_id)
                    ->get(['id', 'name', 'color'])
                : [],
            'branding' => fn () => (function () use ($request) {
                $workspaceId = $request->user()?->workspace_id;
                $workspace   = $workspaceId
                    ? \App\Models\Workspace::find($workspaceId)
                    : \App\Models\Workspace::first();
                $branding = $workspace?->settings['branding'] ?? [];
                return [
                    'name'     => $branding['name'] ?? null,
                    'logo_url' => $branding['logo_url'] ?? null,
                    'website'  => $branding['website'] ?? null,
                ];
            })(),
            'appearance' => fn () => $request->user()
                ? (function () use ($request) {
                    $workspace = \App\Models\Workspace::find($request->user()->workspace_id);
                    $appearance = $workspace?->settings['appearance'] ?? [];

                    return [
                        'mode'     => $appearance['mode'] ?? 'system',
                        'color'    => $appearance['color'] ?? 'violet',
                        'font'     => $appearance['font'] ?? 'figtree',
                        'radius'   => $appearance['radius'] ?? 'sm',
                        'contrast' => $appearance['contrast'] ?? 'balanced',
                    ];
                })()
                : [
                    'mode'     => 'system',
                    'color'    => 'violet',
                    'font'     => 'figtree',
                    'radius'   => 'sm',
                    'contrast' => 'balanced',
                ],
        ]);
    }
}
