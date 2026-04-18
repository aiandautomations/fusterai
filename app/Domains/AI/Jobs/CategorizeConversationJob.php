<?php

namespace App\Domains\AI\Jobs;

use App\Ai\Agents\CategorizationAgent;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Tag;
use App\Domains\Conversation\Models\Thread;
use App\Enums\ConversationPriority;
use App\Services\AiSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;

class CategorizeConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly Conversation $conversation,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $conversation = $this->conversation->load('threads');
        /** @var Thread|null $firstThread */
        $firstThread = $conversation->threads->first();
        if (! $firstThread) {
            return;
        }

        $excerpt = mb_substr(strip_tags((string) $firstThread->body), 0, 500);
        $prompt = "Subject: {$conversation->subject}\nMessage: {$excerpt}";

        ['lab' => $lab, 'model' => $model] = app(AiSettingsService::class)
            ->configureForWorkspace($conversation->workspace_id);

        try {
            $agent = new CategorizationAgent;
            /** @var StructuredAgentResponse $response */
            $response = $agent->prompt($prompt, provider: $lab, model: $model);

            // StructuredAgentResponse implements ArrayAccess, so use array access or toArray()
            $data = $response->toArray();

            // Update priority
            $updates = [];
            if (! empty($data['priority']) && ConversationPriority::tryFrom($data['priority']) !== null) {
                $updates['priority'] = $data['priority'];
            }
            // Save the AI-generated short summary from categorization
            if (! empty($data['summary'])) {
                $updates['ai_summary'] = $data['summary'];
            }
            if ($updates) {
                $conversation->update($updates);
            }

            // Sync tags
            if (! empty($data['tags']) && is_array($data['tags'])) {
                $tagIds = [];
                foreach ($data['tags'] as $tagName) {
                    $tag = Tag::firstOrCreate(
                        ['workspace_id' => $conversation->workspace_id, 'name' => strtolower(trim($tagName))],
                        ['color' => $this->tagColor($tagName)],
                    );
                    $tagIds[] = $tag->id;
                }
                $conversation->tags()->sync($tagIds);
                $conversation->update(['ai_tags' => $data['tags']]);
            }
        } catch (\Throwable $e) {
            Log::error('CategorizeConversationJob failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }

    private function tagColor(string $tag): string
    {
        return match (strtolower($tag)) {
            'billing' => '#f59e0b',
            'bug' => '#ef4444',
            'feature-request' => '#8b5cf6',
            'account' => '#3b82f6',
            'shipping' => '#10b981',
            'refund' => '#f97316',
            'technical' => '#6366f1',
            default => '#6b7280',
        };
    }
}
