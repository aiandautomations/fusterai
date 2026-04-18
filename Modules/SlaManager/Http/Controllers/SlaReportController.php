<?php

namespace Modules\SlaManager\Http\Controllers;

use App\Domains\Mailbox\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\SlaManager\Models\SlaStatus;

class SlaReportController extends Controller
{
    public function index(Request $request)
    {
        $workspaceId = $request->user()->workspace_id;
        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 30, 90]) ? $days : 30;
        $since = now()->subDays($days)->startOfDay();

        // ── Overall stats ─────────────────────────────────────────────────────
        $base = SlaStatus::query()
            ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where('created_at', '>=', $since);

        $total = (clone $base)->count();
        $frBreached = (clone $base)->where('first_response_breached', true)->count();
        $resBreached = (clone $base)->where('resolution_breached', true)->count();
        $frAchievedCount = (clone $base)->whereNotNull('first_response_achieved_at')->count();
        $resAchievedCount = (clone $base)->whereNotNull('resolved_at')->count();

        // Average first response time in minutes (for achieved ones only)
        $avgFirstResponseMinutes = (clone $base)
            ->whereNotNull('first_response_achieved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_response_achieved_at - created_at)) / 60)::int as avg_minutes')
            ->value('avg_minutes') ?? 0;

        $avgResolutionMinutes = (clone $base)
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 60)::int as avg_minutes')
            ->value('avg_minutes') ?? 0;

        // ── Per-mailbox breakdown ─────────────────────────────────────────────
        $byMailbox = SlaStatus::query()
            ->join('conversations', 'sla_statuses.conversation_id', '=', 'conversations.id')
            ->join('mailboxes', 'conversations.mailbox_id', '=', 'mailboxes.id')
            ->where('conversations.workspace_id', $workspaceId)
            ->where('sla_statuses.created_at', '>=', $since)
            ->select(
                'mailboxes.id as mailbox_id',
                'mailboxes.name as mailbox_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN first_response_breached THEN 1 ELSE 0 END) as fr_breached'),
                DB::raw('SUM(CASE WHEN resolution_breached THEN 1 ELSE 0 END) as res_breached'),
            )
            ->groupBy('mailboxes.id', 'mailboxes.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'mailbox_id' => $row->mailbox_id,
                'mailbox_name' => $row->mailbox_name,
                'total' => $row->total,
                'fr_breached' => $row->fr_breached,
                'res_breached' => $row->res_breached,
                'fr_breach_rate' => $row->total > 0 ? round($row->fr_breached / $row->total * 100, 1) : 0,
                'res_breach_rate' => $row->total > 0 ? round($row->res_breached / $row->total * 100, 1) : 0,
            ])
            ->values()
            ->all();

        // ── Per-agent breakdown ───────────────────────────────────────────────
        $byAgent = SlaStatus::query()
            ->join('conversations', 'sla_statuses.conversation_id', '=', 'conversations.id')
            ->join('users', 'conversations.assigned_user_id', '=', 'users.id')
            ->where('conversations.workspace_id', $workspaceId)
            ->where('sla_statuses.created_at', '>=', $since)
            ->select(
                'users.id as user_id',
                'users.name as agent_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN first_response_breached THEN 1 ELSE 0 END) as fr_breached'),
                DB::raw('SUM(CASE WHEN resolution_breached THEN 1 ELSE 0 END) as res_breached'),
                DB::raw('AVG(CASE WHEN first_response_achieved_at IS NOT NULL THEN EXTRACT(EPOCH FROM (first_response_achieved_at - sla_statuses.created_at)) / 60 END)::int as avg_fr_minutes'),
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->user_id,
                'agent_name' => $row->agent_name,
                'total' => $row->total,
                'fr_breached' => $row->fr_breached,
                'res_breached' => $row->res_breached,
                'fr_breach_rate' => $row->total > 0 ? round($row->fr_breached / $row->total * 100, 1) : 0,
                'res_breach_rate' => $row->total > 0 ? round($row->res_breached / $row->total * 100, 1) : 0,
                'avg_fr_minutes' => $row->avg_fr_minutes ?? 0,
            ])
            ->values()
            ->all();

        return Inertia::render('Settings/SlaReport', [
            'days' => $days,
            'total' => $total,
            'fr_breached' => $frBreached,
            'res_breached' => $resBreached,
            'fr_achieved' => $frAchievedCount,
            'res_achieved' => $resAchievedCount,
            'fr_breach_rate' => $total > 0 ? round($frBreached / $total * 100, 1) : 0,
            'res_breach_rate' => $total > 0 ? round($resBreached / $total * 100, 1) : 0,
            'avg_first_response_min' => $avgFirstResponseMinutes,
            'avg_resolution_min' => $avgResolutionMinutes,
            'by_mailbox' => $byMailbox,
            'by_agent' => $byAgent,
        ]);
    }
}
