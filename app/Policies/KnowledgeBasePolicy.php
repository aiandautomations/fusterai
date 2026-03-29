<?php

namespace App\Policies;

use App\Domains\AI\Models\KnowledgeBase;
use App\Models\User;

class KnowledgeBasePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** Manager+ can view and manage the knowledge base. */
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, KnowledgeBase $kb): bool
    {
        return $user->isManager() && $user->workspace_id === $kb->workspace_id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, KnowledgeBase $kb): bool
    {
        return $user->isManager() && $user->workspace_id === $kb->workspace_id;
    }

    public function delete(User $user, KnowledgeBase $kb): bool
    {
        return $user->isManager() && $user->workspace_id === $kb->workspace_id;
    }
}
