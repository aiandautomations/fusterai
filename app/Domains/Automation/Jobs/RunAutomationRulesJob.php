<?php

namespace App\Domains\Automation\Jobs;

use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Folder;
use App\Domains\Conversation\Models\Tag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates all active automation rules for a given trigger + conversation.
 * Dispatched after conversation events (created, replied, closed, etc.).
 */
class RunAutomationRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $trigger,
        public readonly Conversation $conversation,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $rules = AutomationRule::where('workspace_id', $this->conversation->workspace_id)
            ->where('trigger', $this->trigger)
            ->where('active', true)
            ->orderBy('order')
            ->get();

        foreach ($rules as $rule) {
            try {
                if ($this->matchesConditions($rule->conditions)) {
                    $this->executeActions($rule->actions);
                    $rule->increment('run_count');
                    $rule->update(['last_run_at' => now()]);
                }
            } catch (\Throwable $e) {
                Log::error("AutomationRule #{$rule->id} failed: {$e->getMessage()}", [
                    'conversation_id' => $this->conversation->id,
                ]);
            }
        }
    }

    private function matchesConditions(array $conditions): bool
    {
        $conv = $this->conversation;

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? null;

            $actual = match ($field) {
                'status' => $conv->status,
                'priority' => $conv->priority,
                'channel' => $conv->channel_type,
                'subject' => $conv->subject,
                'assigned' => $conv->assigned_user_id !== null ? 'yes' : 'no',
                default => null,
            };

            $matches = match ($operator) {
                'equals' => $actual === $value,
                'not_equals' => $actual !== $value,
                'contains' => is_string($actual) && str_contains(strtolower($actual), strtolower($value)),
                'not_contains' => is_string($actual) && ! str_contains(strtolower($actual), strtolower($value)),
                default => false,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    private function executeActions(array $actions): void
    {
        $conv = $this->conversation;

        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? null;

            match ($type) {
                'set_status' => $conv->update(['status' => $value]),
                'set_priority' => $conv->update(['priority' => $value]),
                'assign_to' => $conv->update(['assigned_user_id' => $value]),
                'unassign' => $conv->update(['assigned_user_id' => null]),
                'add_tag' => $conv->tags()->syncWithoutDetaching(
                    Tag::where('workspace_id', $conv->workspace_id)
                        ->where('name', $value)->pluck('id')
                ),
                'add_to_folder' => $conv->folders()->syncWithoutDetaching(
                    Folder::where('workspace_id', $conv->workspace_id)
                        ->where('id', $value)->pluck('id')
                ),
                'move_mailbox' => $conv->update(['mailbox_id' => $value]),
                default => null,
            };
        }
    }
}
