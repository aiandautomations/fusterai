<?php

namespace App\Services;

use App\Domains\Conversation\Models\CustomView;
use App\Models\User;

class CustomViewService
{
    public function create(array $validated, User $user): CustomView
    {
        $isShared    = (bool) ($validated['is_shared'] ?? false);
        $workspaceId = $user->workspace_id;

        return CustomView::create([
            'workspace_id' => $workspaceId,
            'user_id'      => $isShared ? null : $user->id,
            'name'         => $validated['name'],
            'color'        => $validated['color'],
            'filters'      => $this->stripEmpty($validated['filters']),
            'is_shared'    => $isShared,
            'order'        => CustomView::where('workspace_id', $workspaceId)->max('order') + 1,
        ]);
    }

    public function update(CustomView $view, array $validated): void
    {
        if (isset($validated['filters'])) {
            $validated['filters'] = $this->stripEmpty($validated['filters']);
        }

        $view->update($validated);
    }

    public function delete(CustomView $view): void
    {
        $view->delete();
    }

    private function stripEmpty(array $data): array
    {
        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }
}
