<?php

use App\Domains\AI\Jobs\GenerateReplySuggestionJob;
use App\Domains\AI\Jobs\SummarizeConversationJob;
use App\Domains\Conversation\Models\AiSuggestion;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->workspace = Workspace::factory()->create();
    $this->user      = agentUser($this->workspace);
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer  = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->conv = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);
});

// ── suggestReply ───────────────────────────────────────────────────────────────

test('agent can request an AI reply suggestion', function () {
    $this->actingAs($this->user)
        ->postJson("/ai/conversations/{$this->conv->id}/suggest-reply")
        ->assertOk()
        ->assertJson(['status' => 'queued']);

    Queue::assertPushedOn('ai', GenerateReplySuggestionJob::class);
});

test('cannot request AI reply for another workspace conversation', function () {
    $other = Workspace::factory()->create();
    $conv  = Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $other->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $other->id])->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("/ai/conversations/{$conv->id}/suggest-reply")
        ->assertForbidden();

    Queue::assertNotPushed(GenerateReplySuggestionJob::class);
});

// ── summarize ─────────────────────────────────────────────────────────────────

test('agent can request a conversation summary', function () {
    $this->actingAs($this->user)
        ->postJson("/ai/conversations/{$this->conv->id}/summarize")
        ->assertOk()
        ->assertJson(['status' => 'queued']);

    Queue::assertPushedOn('ai', SummarizeConversationJob::class);
});

test('cannot summarize a conversation from another workspace', function () {
    $other = Workspace::factory()->create();
    $conv  = Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $other->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $other->id])->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("/ai/conversations/{$conv->id}/summarize")
        ->assertForbidden();
});

// ── acceptSuggestion ──────────────────────────────────────────────────────────

test('agent can accept an AI suggestion', function () {
    $suggestion = AiSuggestion::create([
        'conversation_id' => $this->conv->id,
        'type'            => 'reply',
        'content'         => 'Here is a suggested reply.',
        'model'           => 'claude-haiku-4-5-20251001',
        'accepted'        => false,
    ]);

    $this->actingAs($this->user)
        ->patch("/ai/suggestions/{$suggestion->id}/accept")
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($suggestion->fresh()->accepted)->toBeTrue();
});

test('cannot accept a suggestion for another workspace conversation', function () {
    $other = Workspace::factory()->create();
    $conv  = Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $other->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $other->id])->id,
    ]);

    $suggestion = AiSuggestion::create([
        'conversation_id' => $conv->id,
        'type'            => 'reply',
        'content'         => 'Suggestion content.',
        'model'           => 'claude-haiku-4-5-20251001',
        'accepted'        => false,
    ]);

    $this->actingAs($this->user)
        ->patch("/ai/suggestions/{$suggestion->id}/accept")
        ->assertForbidden();

    expect($suggestion->fresh()->accepted)->toBeFalse();
});
