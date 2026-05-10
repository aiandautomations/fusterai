<?php

namespace App\Http\Controllers;

use App\Domains\Conversation\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->workspace_id;
        $userId = $request->user()->id;

        // Single query with conditional aggregates — same pattern as ConversationController::folderCounts()
        $counts = DB::table('conversations')
            ->where('workspace_id', $workspaceId)
            ->selectRaw("
                COUNT(CASE WHEN status = 'open'    THEN 1 END) AS open,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending,
                COUNT(CASE WHEN status = 'open' AND assigned_user_id = ? THEN 1 END) AS mine,
                COUNT(CASE WHEN status = 'open' AND assigned_user_id IS NULL THEN 1 END) AS unassigned
            ", [$userId])
            ->first();

        $trend = Conversation::where('workspace_id', $workspaceId)
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $recent = Conversation::where('workspace_id', $workspaceId)
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
                'open' => (int) ($counts->open ?? 0),
                'pending' => (int) ($counts->pending ?? 0),
                'mine' => (int) ($counts->mine ?? 0),
                'unassigned' => (int) ($counts->unassigned ?? 0),
                'trend' => $trend,
            ],
            'topAgents' => $topAgents,
            'recent' => $recent,
        ]);
    }
}
