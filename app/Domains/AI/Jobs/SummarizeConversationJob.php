<?php

namespace App\Domains\AI\Jobs;

use App\Ai\Agents\SummarizationAgent;
use App\Domains\Conversation\Models\Conversation;
use App\Services\AiSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SummarizeConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly Conversation $conversation,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $conversation = $this->conversation->load(['threads']);

        $transcript = $conversation->threads
            ->where('type', 'message')
            ->map(function ($t) {
                /** @var \App\Domains\Conversation\Models\Thread $t */
                return ($t->isFromCustomer() ? 'Customer' : 'Agent') . ': ' . strip_tags((string) $t->body);
            })
            ->join("\n\n");

        if (empty($transcript)) return;

        ['lab' => $lab, 'model' => $model] = app(AiSettingsService::class)
            ->configureForWorkspace($conversation->workspace_id);

        try {
            $agent    = new SummarizationAgent();
            $response = $agent->prompt($transcript, provider: $lab, model: $model);

            $conversation->update(['ai_summary' => $response->text]);
        } catch (\Throwable $e) {
            Log::error('SummarizeConversationJob failed', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
