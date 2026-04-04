<?php

use App\Ai\Agents\CategorizationAgent;
use App\Ai\Agents\ReplySuggestionAgent;
use App\Ai\Agents\SummarizationAgent;
use App\Domains\AI\Jobs\CategorizeConversationJob;
use App\Domains\AI\Jobs\GenerateReplySuggestionJob;
use App\Domains\AI\Jobs\SummarizeConversationJob;
use App\Domains\Conversation\Models\AiSuggestion;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
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

    expect($this->conversation->fresh()->priority)->toBe('high');
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
