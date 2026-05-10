<?php

namespace App\Services;

use App\Domains\Automation\Models\AutomationRule;
use Illuminate\Support\Facades\DB;

class AutomationService
{
    public function create(array $validated, int $workspaceId): AutomationRule
    {
        return DB::transaction(function () use ($validated, $workspaceId): AutomationRule {
            // Lock the highest-order row so concurrent creates get serialized.
            // Using first() + lockForUpdate() works on PostgreSQL (unlike MAX() FOR UPDATE).
            $last = AutomationRule::where('workspace_id', $workspaceId)
                ->orderByDesc('order')
                ->lockForUpdate()
                ->first();

            return AutomationRule::create([
                ...$validated,
                'workspace_id' => $workspaceId,
                'order' => ($last?->order ?? 0) + 1,
            ]);
        });
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
