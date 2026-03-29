<?php

namespace Modules\SlaManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\SlaManager\Models\SlaStatus;

class CheckSlaBreachJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        $now = now();

        // First response breaches
        SlaStatus::query()
            ->whereNull('first_response_achieved_at')
            ->where('first_response_breached', false)
            ->where('first_response_due_at', '<=', $now)
            ->whereHas('conversation', fn ($q) => $q->whereNotIn('status', ['closed', 'spam']))
            ->chunkById(100, function ($statuses) {
                foreach ($statuses as $status) {
                    $status->update(['first_response_breached' => true]);
                    Log::info('SLA first response breached', [
                        'conversation_id' => $status->conversation_id,
                    ]);
                }
            });

        // Resolution breaches
        SlaStatus::query()
            ->whereNull('resolved_at')
            ->where('resolution_breached', false)
            ->where('resolution_due_at', '<=', $now)
            ->whereHas('conversation', fn ($q) => $q->whereNotIn('status', ['closed', 'spam']))
            ->chunkById(100, function ($statuses) {
                foreach ($statuses as $status) {
                    $status->update(['resolution_breached' => true]);
                    Log::info('SLA resolution breached', [
                        'conversation_id' => $status->conversation_id,
                    ]);
                }
            });
    }
}
