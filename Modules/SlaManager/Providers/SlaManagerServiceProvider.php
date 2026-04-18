<?php

namespace Modules\SlaManager\Providers;

use App\Domains\Conversation\Models\Conversation;
use App\Enums\ConversationStatus;
use App\Enums\ThreadType;
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
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // ── Attach SLA when conversation is created ───────────────────────────
        Hooks::addAction('conversation.created', function (Conversation $conversation) {
            $this->attachSla($conversation);
        });

        // ── Handle priority and status changes ───────────────────────────────
        Hooks::addAction('conversation.updated', function (Conversation $conversation) {
            if ($conversation->wasChanged('priority')) {
                $this->attachSla($conversation, reattach: true);
            }

            if (! $conversation->wasChanged('status')) {
                return;
            }

            // Load SlaStatus once to handle both pause/resume and resolution
            $status = SlaStatus::where('conversation_id', $conversation->id)->first();
            if (! $status) {
                return;
            }

            if ($conversation->status === ConversationStatus::Closed) {
                if (! $status->resolved_at) {
                    $status->update(['resolved_at' => now(), 'paused_at' => null]);
                }
            } elseif ($conversation->status === ConversationStatus::Pending) {
                $status->pause();
            } elseif ($conversation->status === ConversationStatus::Open && $status->isPaused()) {
                $status->resume();
            }
        });

        // ── Mark first response achieved when agent sends a reply ─────────────
        Hooks::addAction('thread.created', function ($thread) {
            if (! $thread->user_id || $thread->type !== ThreadType::Message) {
                return;
            }

            SlaStatus::where('conversation_id', $thread->conversation_id)
                ->whereNull('first_response_achieved_at')
                ->update(['first_response_achieved_at' => now()]);
        });

        // ── Inject SLA data into conversation page props ──────────────────────
        Hooks::addFilter('conversation.show.extra', function (array $data, Conversation $conversation) {
            $status = SlaStatus::with('policy')
                ->where('conversation_id', $conversation->id)
                ->first();

            if ($status) {
                $data['sla'] = [
                    'policy' => $status->policy ? [
                        'name' => $status->policy->name,
                        'first_response_label' => $status->policy->first_response_label,
                        'resolution_label' => $status->policy->resolution_label,
                    ] : null,
                    'first_response_status' => $status->first_response_status,
                    'resolution_status' => $status->resolution_status,
                    'first_response_due_at' => $status->first_response_due_at,
                    'resolution_due_at' => $status->resolution_due_at,
                    'first_response_remaining_minutes' => $status->first_response_remaining_minutes,
                    'resolution_remaining_minutes' => $status->resolution_remaining_minutes,
                    'is_paused' => $status->isPaused(),
                ];
            }

            return $data;
        });

        // ── Add SLA breach context to AI system prompt ────────────────────────
        Hooks::addFilter('ai.system_prompt', function (string $prompt, Conversation $conversation) {
            $status = SlaStatus::where('conversation_id', $conversation->id)->first();

            if ($status && $status->resolution_status === 'breached') {
                $prompt .= "\n\nNote: This conversation has breached its SLA resolution target. Prioritize a prompt, helpful response.";
            }

            return $prompt;
        });

        // ── Schedule breach detection every 5 minutes ────────────────────────
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(CheckSlaBreachJob::class, 'default')->everyFiveMinutes();
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

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

        if ($reattach) {
            $existing = SlaStatus::where('conversation_id', $conversation->id)->first();

            if (! $existing) {
                return;
            }

            $updates = [
                'sla_policy_id' => $policy->id,
                'resolution_due_at' => $createdAt->copy()->addMinutes($policy->resolution_minutes + $existing->pause_offset_minutes),
            ];

            // Only re-calculate first_response_due_at if not yet achieved
            if (! $existing->first_response_achieved_at) {
                $updates['first_response_due_at'] = $createdAt->copy()->addMinutes($policy->first_response_minutes + $existing->pause_offset_minutes);
                // Reset breach flag only if not yet achieved (new policy, new chance)
                $updates['first_response_breached'] = false;
            }

            $updates['resolution_breached'] = false;

            $existing->update($updates);
        } else {
            SlaStatus::firstOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'sla_policy_id' => $policy->id,
                    'first_response_due_at' => $createdAt->copy()->addMinutes($policy->first_response_minutes),
                    'resolution_due_at' => $createdAt->copy()->addMinutes($policy->resolution_minutes),
                ]
            );
        }
    }
}
