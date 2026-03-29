<?php

namespace App\Policies;

use App\Domains\Automation\Models\AutomationRule;
use App\Models\User;

class AutomationRulePolicy
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

    public function update(User $user, AutomationRule $rule): bool
    {
        return $user->isManager() && $user->workspace_id === $rule->workspace_id;
    }

    public function delete(User $user, AutomationRule $rule): bool
    {
        return $user->isManager() && $user->workspace_id === $rule->workspace_id;
    }
}
