<?php

namespace App\Http\Controllers;

use App\Domains\Conversation\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->workspace_id;
        $userId = $request->user()->id;

        $base = Conversation::where('workspace_id', $workspaceId);

        $recent = (clone $base)
            ->with('customer:id,name,email')
            ->orderByDesc('last_reply_at')
            ->limit(5)
            ->get(['id', 'subject', 'status', 'priority', 'customer_id', 'last_reply_at']);

        $topAgents = User::where('workspace_id', $workspaceId)
            ->withCount(['assignedConversations as resolved_count' => fn ($q) => $q->where('status', 'closed')->where('updated_at', '>=', now()->startOfMonth()),
            ])
            ->orderByDesc('resolved_count')
            ->limit(5)
            ->get(['id', 'name', 'email', 'avatar']);

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'open' => (clone $base)->where('status', 'open')->count(),
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'mine' => (clone $base)->where('status', 'open')->where('assigned_user_id', $userId)->count(),
                'unassigned' => (clone $base)->where('status', 'open')->whereNull('assigned_user_id')->count(),
                'trend' => (clone $base)
                    ->where('created_at', '>=', now()->subDays(14))
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
            ],
            'topAgents' => $topAgents,
            'recent' => $recent,
        ]);
    }
}
