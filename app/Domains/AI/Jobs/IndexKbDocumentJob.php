<?php

namespace App\Domains\AI\Jobs;

use App\Domains\AI\Models\KbDocument;
use App\Services\AiSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Embeddings;

class IndexKbDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly KbDocument $document,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $text = $this->document->title . "\n\n" . $this->document->content;
        $text = mb_substr($text, 0, 8000);

        // Apply workspace credentials so the embeddings call uses the
        // admin-configured provider rather than .env defaults.
        ['lab' => $lab] = app(AiSettingsService::class)
            ->configureForWorkspace($this->document->knowledgeBase->workspace_id);

        $response = Embeddings::for([$text])->generate($lab);

        $this->document->embedding  = $response->first();
        $this->document->indexed_at = now();
        $this->document->save();
    }
}
