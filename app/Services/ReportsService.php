<?php

namespace App\Services;

use App\Domains\Conversation\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(int $workspaceId, int $days): array
    {
        $since = now()->subDays($days);
        $base = Conversation::where('workspace_id', $workspaceId);

        $counts = DB::table('conversations')
            ->where('workspace_id', $workspaceId)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'open'    THEN 1 END) as open,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'closed'  THEN 1 END) as closed
            ")
            ->first();

        return [
            'total' => $counts->total,
            'open' => $counts->open,
            'pending' => $counts->pending,
            'closed' => $counts->closed,

            'trend' => (clone $base)
                ->where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            'by_channel' => (clone $base)
                ->selectRaw('channel_type, COUNT(*) as count')
                ->groupBy('channel_type')
                ->orderByDesc('count')
                ->get(),

            'by_mailbox' => (clone $base)
                ->whereNotNull('mailbox_id')
                ->selectRaw('mailbox_id, COUNT(*) as count')
                ->with('mailbox:id,name')
                ->groupBy('mailbox_id')
                ->orderByDesc('count')
                ->get(),

            'by_priority' => (clone $base)
                ->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->get(),

            'top_agents' => User::where('workspace_id', $workspaceId)
                ->withCount(['assignedConversations as resolved_count' => fn ($q) => $q->where('status', 'closed')->where('updated_at', '>=', $since),
                ])
                ->orderByDesc('resolved_count')
                ->limit(5)
                ->get(['id', 'name', 'email']),

            'avg_resolution_hours' => round(
                (clone $base)
                    ->where('status', 'closed')
                    ->where('created_at', '>=', $since)
                    ->whereNotNull('last_reply_at')
                    ->selectRaw('AVG(EXTRACT(EPOCH FROM (last_reply_at - created_at))/3600) as avg_hours')
                    ->value('avg_hours') ?? 0,
                1
            ),
        ];
    }
}
