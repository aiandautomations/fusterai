<?php

use App\Ai\Agents\CategorizationAgent;
use App\Ai\Agents\ReplySuggestionAgent;
use App\Ai\Agents\SummarizationAgent;
use App\Domains\AI\Jobs\CategorizeConversationJob;
use App\Domains\AI\Jobs\FetchUrlAndIndexJob;
use App\Domains\AI\Jobs\GenerateReplySuggestionJob;
use App\Domains\AI\Jobs\IndexKbDocumentJob;
use App\Domains\AI\Jobs\SummarizeConversationJob;
use App\Domains\AI\Models\KbDocument;
use App\Domains\AI\Models\KnowledgeBase;
use App\Domains\Conversation\Models\AiSuggestion;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Events\AiSuggestionFailed;
use App\Models\Workspace;
use App\Support\SsrfGuard;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;

beforeEach(function () {
    $this->workspace    = Workspace::factory()->create();
    $this->mailbox      = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer     = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'body'            => '<p>My order has not arrived.</p>',
    ]);
});

test('CategorizeConversationJob sets priority and tags using faked agent', function () {
    CategorizationAgent::fake([
        new StructuredAgentResponse(
            invocationId: 'test-id',
            structured: ['priority' => 'high', 'tags' => ['shipping'], 'summary' => 'Order not arrived'],
            text: '{}',
            usage: new Usage,
            meta: new Meta('anthropic', 'claude-haiku-4-5-20251001'),
        ),
    ]);

    (new CategorizeConversationJob($this->conversation))->handle();

    expect($this->conversation->fresh()->priority->value)->toBe('high');
    CategorizationAgent::assertPrompted(fn ($prompt) => true);
});

test('SummarizeConversationJob saves summary using faked agent', function () {
    SummarizationAgent::fake([
        new AgentResponse(
            invocationId: 'test-id',
            text: '• Customer reported missing order\n• No resolution yet',
            usage: new Usage,
            meta: new Meta('anthropic', 'claude-haiku-4-5-20251001'),
        ),
    ]);

    (new SummarizeConversationJob($this->conversation))->handle();

    expect($this->conversation->fresh()->ai_summary)->toContain('Customer reported');
    SummarizationAgent::assertPrompted(fn ($prompt) => true);
});

test('GenerateReplySuggestionJob creates AiSuggestion record', function () {
    ReplySuggestionAgent::fake(['Here is a helpful reply for you.']);

    (new GenerateReplySuggestionJob($this->conversation))->handle();

    expect(
        AiSuggestion::where('conversation_id', $this->conversation->id)->exists()
    )->toBeTrue();
});

test('GenerateReplySuggestionJob marks job as failed when AI throws', function () {
    ReplySuggestionAgent::fake(function () {
        throw new \RuntimeException('AI provider unavailable');
    });

    $job = \Mockery::mock(GenerateReplySuggestionJob::class . '[fail]', [$this->conversation])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $job->shouldReceive('fail')->once()->with(\Mockery::type(\RuntimeException::class));

    $job->handle();
});

test('AI feature flag disabled for workspace skips reply suggestion job', function () {
    $this->workspace->update([
        'settings' => ['ai_features' => ['reply_suggestions' => false, 'auto_categorization' => true]],
    ]);

    \Illuminate\Support\Facades\Queue::fake();

    // Simulate what ProcessInboundEmailJob does
    $ai = app(\App\Services\AiSettingsService::class);
    if ($ai->isFeatureEnabled($this->workspace->id, 'reply_suggestions')) {
        GenerateReplySuggestionJob::dispatch($this->conversation)->onQueue('ai');
    }

    \Illuminate\Support\Facades\Queue::assertNotPushed(GenerateReplySuggestionJob::class);
});

test('AI feature flag enabled for workspace dispatches reply suggestion job', function () {
    $this->workspace->update([
        'settings' => ['ai_features' => ['reply_suggestions' => true, 'auto_categorization' => true]],
    ]);

    \Illuminate\Support\Facades\Queue::fake();

    $ai = app(\App\Services\AiSettingsService::class);
    if ($ai->isFeatureEnabled($this->workspace->id, 'reply_suggestions')) {
        GenerateReplySuggestionJob::dispatch($this->conversation)->onQueue('ai');
    }

    \Illuminate\Support\Facades\Queue::assertPushedOn('ai', GenerateReplySuggestionJob::class);
});

// ── failed() callbacks ─────────────────────────────────────────────────────────

test('GenerateReplySuggestionJob failed() broadcasts AiSuggestionFailed event', function () {
    Event::fake([AiSuggestionFailed::class]);

    $job = new GenerateReplySuggestionJob($this->conversation);
    $job->failed(new \RuntimeException('Provider error'));

    Event::assertDispatched(AiSuggestionFailed::class, function ($event) {
        return $event->conversationId === $this->conversation->id;
    });
});

test('IndexKbDocumentJob failed() stores error message in document meta', function () {
    $kb  = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);
    $doc = $kb->documents()->create(['title' => 'Guide', 'content' => 'Content here.']);

    $job = new IndexKbDocumentJob($doc);
    $job->failed(new \RuntimeException('No embeddings API key'));

    expect($doc->fresh()->meta['index_error'])->toBe('No embeddings API key');
});

test('IndexKbDocumentJob handle() clears index_error on successful re-index', function () {
    // Fake with no preset responses — SDK auto-generates vectors of the right dimensions
    \Laravel\Ai\Embeddings::fake();

    $kb  = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);
    $doc = $kb->documents()->create([
        'title'   => 'Guide',
        'content' => 'Content here.',
        'meta'    => ['index_error' => 'Previous failure'],
    ]);

    (new IndexKbDocumentJob($doc))->handle();

    $fresh = $doc->fresh();
    expect($fresh->indexed_at)->not->toBeNull();
    expect(array_key_exists('index_error', $fresh->meta ?? []))->toBeFalse();
});

// ── FetchUrlAndIndexJob ────────────────────────────────────────────────────────

test('FetchUrlAndIndexJob fetches URL and dispatches IndexKbDocumentJob', function () {
    Queue::fake();

    Http::fake([
        'example.com/*' => Http::response(
            '<html><head><title>Help Article</title></head><body><p>This is the article content.</p></body></html>',
            200,
        ),
    ]);

    $kb = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);

    (new FetchUrlAndIndexJob($kb, 'https://example.com/article'))->handle();

    expect(KbDocument::where('kb_id', $kb->id)->where('title', 'Help Article')->exists())->toBeTrue();
    Queue::assertPushed(IndexKbDocumentJob::class);
});

test('FetchUrlAndIndexJob fails when HTTP response is non-200', function () {
    Http::fake(['example.com/*' => Http::response('Not Found', 404)]);

    $kb  = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);
    $job = \Mockery::mock(FetchUrlAndIndexJob::class . '[fail]', [$kb, 'https://example.com/missing'])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $job->shouldReceive('fail')->once();
    $job->handle();
});

// ── SsrfGuard ─────────────────────────────────────────────────────────────────

test('SsrfGuard blocks private IPv4 addresses', function () {
    expect(fn () => SsrfGuard::validate('http://192.168.1.1/admin'))
        ->toThrow(\InvalidArgumentException::class, 'private or reserved');

    expect(fn () => SsrfGuard::validate('http://10.0.0.1/secret'))
        ->toThrow(\InvalidArgumentException::class, 'private or reserved');

    expect(fn () => SsrfGuard::validate('http://127.0.0.1/'))
        ->toThrow(\InvalidArgumentException::class, 'private or reserved');
});

test('SsrfGuard blocks AWS instance metadata endpoint', function () {
    expect(fn () => SsrfGuard::validate('http://169.254.169.254/latest/meta-data/'))
        ->toThrow(\InvalidArgumentException::class);
});

test('SsrfGuard blocks non-http schemes', function () {
    expect(fn () => SsrfGuard::validate('file:///etc/passwd'))
        ->toThrow(\InvalidArgumentException::class, 'http and https');

    expect(fn () => SsrfGuard::validate('ftp://internal.server/'))
        ->toThrow(\InvalidArgumentException::class, 'http and https');
});

test('SsrfGuard allows public URLs', function () {
    // Should not throw — example.com resolves to a public IP
    $threw = false;
    try {
        SsrfGuard::validate('https://example.com/page');
    } catch (\InvalidArgumentException) {
        $threw = true;
    }
    expect($threw)->toBeFalse();
});
