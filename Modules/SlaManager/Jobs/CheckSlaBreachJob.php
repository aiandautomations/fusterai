<?php

namespace Modules\SlaManager\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\SlaManager\Models\SlaStatus;
use Modules\SlaManager\Notifications\SlaBreachedNotification;

class CheckSlaBreachJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        $now = now();

        // ── First Response Breaches ───────────────────────────────────────────
        SlaStatus::query()
            ->with('conversation.assignedUser')
            ->whereNull('first_response_achieved_at')
            ->where('first_response_breached', false)
            ->whereNull('paused_at')
            ->where('first_response_due_at', '<=', $now)
            ->whereHas('conversation', fn ($q) => $q->whereNotIn('status', ['closed', 'spam']))
            ->chunkById(100, function ($statuses) {
                foreach ($statuses as $status) {
                    $status->update(['first_response_breached' => true]);

                    $conversation = $status->conversation;

                    // Activity thread in the conversation timeline
                    $conversation->threads()->create([
                        'type'       => 'activity',
                        'body'       => '<p>⚠️ SLA first response target has been breached.</p>',
                        'body_plain' => 'SLA first response target has been breached.',
                        'source'     => 'web',
                    ]);

                    // Notify assigned agent; fall back to workspace admins
                    $this->notifyAboutBreach($status, 'first_response');

                    Log::warning('SLA first response breached', [
                        'conversation_id' => $conversation->id,
                        'workspace_id'    => $conversation->workspace_id,
                    ]);
                }
            });

        // ── Resolution Breaches ───────────────────────────────────────────────
        SlaStatus::query()
            ->with('conversation.assignedUser')
            ->whereNull('resolved_at')
            ->where('resolution_breached', false)
            ->whereNull('paused_at')
            ->where('resolution_due_at', '<=', $now)
            ->whereHas('conversation', fn ($q) => $q->whereNotIn('status', ['closed', 'spam']))
            ->chunkById(100, function ($statuses) {
                foreach ($statuses as $status) {
                    $status->update(['resolution_breached' => true]);

                    $conversation = $status->conversation;

                    // Activity thread in the conversation timeline
                    $conversation->threads()->create([
                        'type'       => 'activity',
                        'body'       => '<p>⚠️ SLA resolution target has been breached.</p>',
                        'body_plain' => 'SLA resolution target has been breached.',
                        'source'     => 'web',
                    ]);

                    $this->notifyAboutBreach($status, 'resolution');

                    Log::warning('SLA resolution breached', [
                        'conversation_id' => $conversation->id,
                        'workspace_id'    => $conversation->workspace_id,
                    ]);
                }
            });
    }

    private function notifyAboutBreach(SlaStatus $status, string $breachType): void
    {
        $conversation = $status->conversation;
        $notification = new SlaBreachedNotification($conversation, $breachType);

        // Notify the assigned agent first
        if ($conversation->assignedUser) {
            $conversation->assignedUser->notify($notification);

            return;
        }

        // Fall back to all admins in the workspace
        User::where('workspace_id', $conversation->workspace_id)
            ->where('role', 'admin')
            ->each(fn (User $admin) => $admin->notify($notification));
    }
}
