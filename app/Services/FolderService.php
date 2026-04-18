<?php

namespace App\Services;

use App\Domains\Conversation\Models\Folder;
use App\Models\User;

class FolderService
{
    public function create(array $validated, int $workspaceId, User $user): Folder
    {
        return Folder::create([
            'workspace_id' => $workspaceId,
            'created_by_user_id' => $user->id,
            'name' => $validated['name'],
            'color' => $validated['color'],
            'icon' => $validated['icon'] ?? 'folder',
            'order' => Folder::where('workspace_id', $workspaceId)->max('order') + 1,
        ]);
    }

    public function update(Folder $folder, array $validated): void
    {
        $folder->update($validated);
    }

    public function delete(Folder $folder): void
    {
        $folder->delete();
    }
}
