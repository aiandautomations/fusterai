<?php

namespace App\Policies;

use App\Domains\Conversation\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->isManager() && $user->workspace_id === $tag->workspace_id;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->isManager() && $user->workspace_id === $tag->workspace_id;
    }
}
