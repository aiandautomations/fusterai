<?php

namespace App\Policies;

use App\Domains\Conversation\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->isSuperAdmin()) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Folder $folder): bool
    {
        return $user->isManager() && $user->workspace_id === $folder->workspace_id;
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $user->isManager() && $user->workspace_id === $folder->workspace_id;
    }
}
