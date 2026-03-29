<?php

namespace App\Domains\AI\Jobs;

use App\Ai\Agents\ReplySuggestionAgent;
use App\Domains\Conversation\Models\AiSuggestion;
use App\Domains\Conversation\Models\Conversation;
use App\Events\AiSuggestionReady;
use App\Services\AiSettingsService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReplySuggestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly Conversation $conversation,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $conversation = $this->conversation->load(['customer', 'mailbox', 'threads' => fn ($q) => $q->latest()->limit(10)]);

        // Apply workspace AI credentials to runtime config and get the
        // provider + model to pass to the agent via the official SDK API.
        ['lab' => $lab, 'model' => $model] = app(AiSettingsService::class)
            ->configureForWorkspace($conversation->workspace_id);

        try {
            $agent  = new ReplySuggestionAgent($conversation);
            $prompt = 'Please suggest a helpful, professional reply to the latest customer message. Write only the reply body.';

            $channel = new PrivateChannel("conversation.{$conversation->id}");

            $stream = $agent->broadcast($prompt, $channel, provider: $lab, model: $model);

            // broadcast() iterates the stream (broadcasting each chunk) and
            // populates $stream->text and $stream->usage after completion.
            $content = $stream->text ?? '';

            AiSuggestion::create([
                'conversation_id'   => $conversation->id,
                'type'              => 'reply',
                'content'           => $content,
                'model'             => $model ?? $lab->value,
                'prompt_tokens'     => $stream->usage->promptTokens ?? 0,
                'completion_tokens' => $stream->usage->completionTokens ?? 0,
            ]);

            broadcast(new AiSuggestionReady($conversation->id, $content));
        } catch (\Throwable $e) {
            Log::error('GenerateReplySuggestionJob failed', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }
}
