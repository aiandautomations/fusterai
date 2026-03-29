<?php

namespace App\Ai\Tools;

use App\Domains\AI\Models\KbDocument;
use App\Domains\AI\Models\KnowledgeBase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchKnowledgeBase implements Tool
{
    private float $minScore;
    private int   $topK;

    public function __construct(
        private readonly int $workspaceId,
    ) {
        $workspace      = \App\Models\Workspace::find($workspaceId);
        $rag            = $workspace?->settings['ai_rag'] ?? [];
        $this->minScore = (float) ($rag['min_score'] ?? config('ai.rag.min_score', 0.6));
        $this->topK     = (int)   ($rag['top_k']     ?? config('ai.rag.top_k', 5));
    }

    public function description(): string
    {
        return 'Search the knowledge base for documentation, FAQs, and policy articles relevant to the customer\'s question. Use this when the customer asks about features, pricing, policies, or procedures.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query — describe what you are looking for')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $query = $request->string('query');

        // Use vector similarity if embeddings exist, otherwise fall back to full-text search
        $kbIds = KnowledgeBase::where('workspace_id', $this->workspaceId)
            ->where('active', true)
            ->pluck('id');

        if ($kbIds->isEmpty()) {
            return 'No knowledge base found for this workspace.';
        }

        // Try vector search first (requires embeddings to be indexed)
        $hasEmbeddings = KbDocument::whereIn('kb_id', $kbIds)
            ->whereNotNull('embedding')
            ->exists();

        if ($hasEmbeddings) {
            $docs = KbDocument::whereIn('kb_id', $kbIds)
                ->whereNotNull('embedding')
                ->whereVectorSimilarTo('embedding', $query, minSimilarity: $this->minScore)
                ->limit($this->topK)
                ->get(['title', 'content']);
        } else {
            // Fall back to case-insensitive full-text (LIKE is case-insensitive in SQLite, use ILIKE in Postgres)
            $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $docs = KbDocument::whereIn('kb_id', $kbIds)
                ->where(function ($q) use ($query, $operator) {
                    $q->where('title', $operator, "%{$query}%")
                      ->orWhere('content', $operator, "%{$query}%");
                })
                ->limit($this->topK)
                ->get(['title', 'content']);
        }

        if ($docs->isEmpty()) {
            return 'No relevant articles found in the knowledge base.';
        }

        return $docs->map(fn ($doc) =>
            "## {$doc->title}\n\n" . mb_substr($doc->content, 0, 800)
        )->join("\n\n---\n\n");
    }
}
