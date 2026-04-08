<?php

namespace App\Policies;

use App\Models\CannedResponse;
use App\Models\User;

class CannedResponsePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function update(User $user, CannedResponse $cannedResponse): bool
    {
        return $user->workspace_id === $cannedResponse->workspace_id;
    }

    public function delete(User $user, CannedResponse $cannedResponse): bool
    {
        return $user->workspace_id === $cannedResponse->workspace_id;
    }
}
