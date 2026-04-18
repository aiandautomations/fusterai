<?php

namespace App\Domains\AI\Jobs;

use App\Domains\AI\Models\KbDocument;
use App\Domains\AI\Models\KnowledgeBase;
use App\Support\SsrfGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchUrlAndIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public readonly KnowledgeBase $knowledgeBase,
        public readonly string $url,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        try {
            // Guard against SSRF before making any outbound request.
            SsrfGuard::validate($this->url);

            $response = Http::timeout(20)
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'FusterAI-KnowledgeBase/1.0'])
                ->get($this->url);

            // If the server redirects, validate the destination before following.
            if (in_array($response->status(), [301, 302, 307, 308])) {
                $location = $response->header('Location');
                if ($location) {
                    SsrfGuard::validate($location);
                    $response = Http::timeout(20)
                        ->withoutRedirecting()
                        ->withHeaders(['User-Agent' => 'FusterAI-KnowledgeBase/1.0'])
                        ->get($location);
                }
            }

            if (! $response->successful()) {
                Log::warning('FetchUrlAndIndexJob: non-200 response', [
                    'url' => $this->url,
                    'status' => $response->status(),
                ]);
                $this->fail(new \RuntimeException("HTTP {$response->status()} fetching URL"));

                return;
            }

            $html = $response->body();

            // Extract title from <title> tag
            $title = $this->url;
            if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $matches)) {
                $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if (empty($title)) {
                $title = $this->url;
            }

            // Strip scripts, styles, and nav/footer noise before extracting text
            $html = preg_replace('/<(script|style|nav|footer|header|aside)[^>]*>.*?<\/\1>/si', ' ', $html) ?? $html;
            $content = strip_tags($html);
            // Collapse whitespace
            $content = preg_replace('/\s+/', ' ', $content) ?? $content;
            $content = trim($content);

            if (empty($content)) {
                $this->fail(new \RuntimeException('No extractable text content at URL'));

                return;
            }

            // Truncate to a reasonable document size (≈ 50k chars)
            $content = mb_substr($content, 0, 50000);

            // Re-use an existing document for this URL so re-importing refreshes
            // content rather than creating duplicates.
            /** @var KbDocument $document */
            $document = $this->knowledgeBase->documents()->updateOrCreate(
                ['source_url' => $this->url],
                [
                    'title' => mb_substr($title, 0, 255),
                    'content' => $content,
                    'indexed_at' => null, // cleared so the KB Show page shows "Indexing…"
                ],
            );

            IndexKbDocumentJob::dispatch($document);
        } catch (\Throwable $e) {
            Log::error('FetchUrlAndIndexJob failed', [
                'kb_id' => $this->knowledgeBase->id,
                'url' => $this->url,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }
}
