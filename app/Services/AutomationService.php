<?php

namespace App\Services;

use App\Domains\Automation\Models\AutomationRule;

class AutomationService
{
    public function create(array $validated, int $workspaceId): AutomationRule
    {
        return AutomationRule::create([
            ...$validated,
            'workspace_id' => $workspaceId,
            'order' => AutomationRule::where('workspace_id', $workspaceId)->max('order') + 1,
        ]);
    }

    public function update(AutomationRule $rule, array $validated): void
    {
        $rule->update($validated);
    }

    public function toggle(AutomationRule $rule): void
    {
        $rule->update(['active' => ! $rule->active]);
    }

    public function delete(AutomationRule $rule): void
    {
        $rule->delete();
    }
}
