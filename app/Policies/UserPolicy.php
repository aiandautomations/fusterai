<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** Any role can view the team list. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Any workspace member can view another member's profile. */
    public function view(User $user, User $target): bool
    {
        return $user->workspace_id === $target->workspace_id;
    }

    /** Only admin+ can invite new users. */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Only admin+ can update another user. A user cannot promote anyone above their own role. */
    public function update(User $user, User $target): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }
        if ($user->workspace_id !== $target->workspace_id) {
            return false;
        }

        // Prevent privilege escalation — cannot assign a role higher than your own
        return ($user::ROLE_HIERARCHY[$user->role] ?? 0) >= ($user::ROLE_HIERARCHY[$target->role] ?? 0);
    }

    /** Only admin+ can remove users. Cannot remove yourself. */
    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin()
            && $user->workspace_id === $target->workspace_id
            && $user->id !== $target->id;
    }
}
