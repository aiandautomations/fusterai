<?php

namespace App\Policies;

use App\Domains\Customer\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->workspace_id === $customer->workspace_id;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->workspace_id === $customer->workspace_id;
    }
}
