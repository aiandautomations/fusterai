<?php

namespace Modules\ConversationRouting\Models;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoutingConfig extends Model
{
    protected $fillable = [
        'workspace_id',
        'mailbox_id',
        'mode',
        'active',
        'last_assigned_user_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function lastAssignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_assigned_user_id');
    }

    /**
     * Find the routing config for a conversation — mailbox-specific first,
     * then workspace-level fallback.
     */
    public static function forConversation(Conversation $conversation): ?self
    {
        return self::where('workspace_id', $conversation->workspace_id)
            ->where('active', true)
            ->where(function ($q) use ($conversation) {
                $q->where('mailbox_id', $conversation->mailbox_id)
                    ->orWhereNull('mailbox_id');
            })
            ->orderByRaw('mailbox_id IS NULL ASC') // mailbox-specific wins
            ->first();
    }

    /**
     * Return the ordered list of agents eligible for this config.
     * For a mailbox-specific config: agents assigned to that mailbox.
     * For a workspace-level config: all agents in the workspace.
     */
    public function eligibleAgents(): Collection
    {
        $query = User::where('workspace_id', $this->workspace_id)
            ->whereIn('role', ['agent', 'admin']);

        if ($this->mailbox_id) {
            $query->whereHas('mailboxes', fn ($q) => $q->where('mailbox_id', $this->mailbox_id));
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Pick the next agent using round-robin and advance the pointer.
     * Returns null if no eligible agents exist.
     */
    public function nextRoundRobinAgent(): ?User
    {
        $agents = $this->eligibleAgents();

        if ($agents->isEmpty()) {
            return null;
        }

        $lastId = $this->last_assigned_user_id;
        $ids = $agents->pluck('id');

        // Find the position after the last assigned agent
        $currentIndex = $ids->search($lastId);
        $nextIndex = ($currentIndex === false) ? 0 : ($currentIndex + 1) % $agents->count();

        $agent = $agents->get($nextIndex);

        $this->update(['last_assigned_user_id' => $agent->id]);

        return $agent;
    }

    /**
     * Pick the agent with the fewest open conversations in this workspace.
     */
    public function leastLoadedAgent(): ?User
    {
        $agents = $this->eligibleAgents();

        if ($agents->isEmpty()) {
            return null;
        }

        $agentIds = $agents->pluck('id');

        $openCounts = DB::table('conversations')
            ->where('workspace_id', $this->workspace_id)
            ->where('status', 'open')
            ->whereIn('assigned_user_id', $agentIds)
            ->groupBy('assigned_user_id')
            ->selectRaw('assigned_user_id, COUNT(*) as open_count')
            ->pluck('open_count', 'assigned_user_id');

        return $agents->sortBy(fn (User $user) => (int) $openCounts->get($user->id, 0))->first();
    }
}
