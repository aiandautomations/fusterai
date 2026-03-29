<?php

namespace Modules\SlaManager\Providers;

use App\Domains\Conversation\Models\Conversation;
use App\Support\Hooks;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\SlaManager\Jobs\CheckSlaBreachJob;
use Modules\SlaManager\Models\SlaPolicy;
use Modules\SlaManager\Models\SlaStatus;

class SlaManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Routes are loaded centrally by ModuleServiceProvider so they are always
        // available without a server restart. Migrations are also registered there
        // but we keep this call for self-documentation / standalone module use.
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // Create SLA status record when a conversation is created
        Hooks::addAction('conversation.created', function (Conversation $conversation) {
            $this->attachSla($conversation);
        });

        // Re-attach SLA if priority changes
        Hooks::addAction('conversation.updated', function (Conversation $conversation) {
            if ($conversation->wasChanged('priority')) {
                $this->attachSla($conversation, reattach: true);
            }
        });

        // Mark first response achieved when agent replies
        Hooks::addAction('thread.created', function ($thread) {
            if (! $thread->user_id || $thread->type !== 'message') {
                return;
            }

            $status = SlaStatus::where('conversation_id', $thread->conversation_id)
                ->whereNull('first_response_achieved_at')
                ->first();

            $status?->update(['first_response_achieved_at' => now()]);
        });

        // Mark resolved when conversation is closed
        Hooks::addAction('conversation.updated', function (Conversation $conversation) {
            if ($conversation->wasChanged('status') && $conversation->status === 'closed') {
                SlaStatus::where('conversation_id', $conversation->id)
                    ->whereNull('resolved_at')
                    ->update(['resolved_at' => now()]);
            }
        });

        // Inject SLA data into conversation API responses
        Hooks::addFilter('conversation.show.extra', function (array $data, Conversation $conversation) {
            $status = SlaStatus::with('policy')
                ->where('conversation_id', $conversation->id)
                ->first();

            if ($status) {
                $data['sla'] = [
                    'policy'                          => $status->policy ? [
                        'name'                    => $status->policy->name,
                        'first_response_label'    => $status->policy->first_response_label,
                        'resolution_label'        => $status->policy->resolution_label,
                    ] : null,
                    'first_response_status'           => $status->first_response_status,
                    'resolution_status'               => $status->resolution_status,
                    'first_response_due_at'           => $status->first_response_due_at,
                    'resolution_due_at'               => $status->resolution_due_at,
                    'first_response_remaining_minutes' => $status->first_response_remaining_minutes,
                    'resolution_remaining_minutes'    => $status->resolution_remaining_minutes,
                ];
            }

            return $data;
        });

        // Add SLA context to AI system prompt
        Hooks::addFilter('ai.system_prompt', function (string $prompt, Conversation $conversation) {
            $status = SlaStatus::where('conversation_id', $conversation->id)->first();

            if ($status && $status->resolution_status === 'breached') {
                $prompt .= "\n\nNote: This conversation has breached its SLA resolution target. Prioritize a prompt, helpful response.";
            }

            return $prompt;
        });

        // Schedule breach detection every 5 minutes
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(CheckSlaBreachJob::class, 'default')->everyFiveMinutes();
        });
    }

    private function attachSla(Conversation $conversation, bool $reattach = false): void
    {
        $policy = SlaPolicy::where('workspace_id', $conversation->workspace_id)
            ->where('priority', $conversation->priority)
            ->where('active', true)
            ->first();

        if (! $policy) {
            return;
        }

        $createdAt = $conversation->created_at ?? now();

        $attributes = [
            'sla_policy_id'          => $policy->id,
            'first_response_due_at'  => $createdAt->copy()->addMinutes($policy->first_response_minutes),
            'resolution_due_at'      => $createdAt->copy()->addMinutes($policy->resolution_minutes),
        ];

        if ($reattach) {
            SlaStatus::where('conversation_id', $conversation->id)
                ->update($attributes);
        } else {
            SlaStatus::firstOrCreate(
                ['conversation_id' => $conversation->id],
                $attributes
            );
        }
    }
}
