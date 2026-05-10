<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Events\AgentTyping;
use App\Events\VisitorTyping;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'visitor_test-visitor@livechat.local',
    ]);
    $this->agent = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);

    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'channel_type' => 'chat',
        'status' => 'open',
    ]);
});

// ── Visitor typing → agent ────────────────────────────────────────────────────

test('visitor typing event is broadcast on valid conversation', function () {
    Event::fake([VisitorTyping::class]);

    $this->postJson('/api/livechat/typing', [
        'workspace_id' => $this->workspace->id,
        'conversation_id' => $this->conversation->id,
        'visitor_id' => 'test-visitor',
    ])->assertOk()->assertJson(['ok' => true]);

    Event::assertDispatched(VisitorTyping::class, fn ($e) => $e->conversationId === $this->conversation->id);
});

test('visitor typing rejects missing conversation_id', function () {
    $this->postJson('/api/livechat/typing', [])
        ->assertUnprocessable();
});

test('visitor typing rejects non-existent conversation', function () {
    $this->postJson('/api/livechat/typing', ['conversation_id' => 99999])
        ->assertUnprocessable();
});

// ── Agent typing → visitor ────────────────────────────────────────────────────

test('agent typing event is broadcast for own workspace conversation', function () {
    Event::fake([AgentTyping::class]);

    $this->actingAs($this->agent)
        ->postJson("/live-chat/{$this->conversation->id}/typing")
        ->assertOk()->assertJson(['ok' => true]);

    Event::assertDispatched(AgentTyping::class, function ($e) {
        return $e->conversationId === $this->conversation->id
            && $e->agentName === $this->agent->name;
    });
});

test('agent typing is forbidden for conversation in another workspace', function () {
    Event::fake([AgentTyping::class]);

    $otherWorkspace = Workspace::factory()->create();
    $otherConversation = Conversation::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'channel_type' => 'chat',
    ]);

    $this->actingAs($this->agent)
        ->postJson("/live-chat/{$otherConversation->id}/typing")
        ->assertForbidden();

    Event::assertNotDispatched(AgentTyping::class);
});

test('agent typing requires authentication', function () {
    $this->postJson("/live-chat/{$this->conversation->id}/typing")
        ->assertUnauthorized();
});

// ── Event broadcast structure ─────────────────────────────────────────────────

test('VisitorTyping broadcasts on correct public channel', function () {
    $event = new VisitorTyping(42);

    expect($event->broadcastAs())->toBe('visitor.typing');
    expect($event->broadcastWith())->toBe(['conversation_id' => 42]);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('livechat.42');
});

test('AgentTyping broadcasts on correct public channel', function () {
    $event = new AgentTyping(42, 'Alice');

    expect($event->broadcastAs())->toBe('agent.typing');
    expect($event->broadcastWith())->toBe(['conversation_id' => 42, 'agent_name' => 'Alice']);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('livechat.42');
});
