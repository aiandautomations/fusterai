<?php

namespace App\Policies;

use App\Domains\Conversation\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /** Super-admins bypass all checks. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** Any workspace member can view a conversation. */
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->workspace_id === $conversation->workspace_id;
    }

    /**
     * Covers: reply, status change, priority, assign, snooze, merge, sync tags.
     * All roles (agent+) can perform these on conversations in their workspace.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $user->workspace_id === $conversation->workspace_id;
    }
}
