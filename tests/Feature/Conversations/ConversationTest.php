<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user      = agentUser($this->workspace);
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer  = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('conversations index requires auth', function () {
    $this->get('/conversations')->assertRedirect('/login');
});

test('agent can view conversations list', function () {
    Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $this->actingAs($this->user)
        ->get('/conversations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Conversations/Index'));
});

test('agent can view a conversation', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $this->actingAs($this->user)
        ->get("/conversations/{$conversation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Conversations/Show'));
});

test('agent cannot view conversation from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $conversation   = Conversation::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $otherWorkspace->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $otherWorkspace->id])->id,
    ]);

    $this->actingAs($this->user)
        ->get("/conversations/{$conversation->id}")
        ->assertForbidden();
});

test('agent can update conversation status', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'open',
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/status", ['status' => 'closed'])
        ->assertRedirect();

    expect($conversation->fresh()->status->value)->toBe('closed');
});

test('agent can update conversation priority', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/priority", ['priority' => 'urgent'])
        ->assertRedirect();

    expect($conversation->fresh()->priority->value)->toBe('urgent');
});

test('agent can assign conversation to themselves', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/assign", ['user_id' => $this->user->id])
        ->assertRedirect();

    expect($conversation->fresh()->assigned_user_id)->toBe($this->user->id);
});

// ── Status validation ─────────────────────────────────────────────────────────

test('updateStatus rejects invalid status', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'open',
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/status", ['status' => 'invalid'])
        ->assertSessionHasErrors('status');

    expect($conversation->fresh()->status->value)->toBe('open');
});

test('updateStatus accepts all valid statuses', function () {
    Queue::fake();
    Event::fake();

    foreach (array_column(ConversationStatus::cases(), 'value') as $status) {
        $conversation = Conversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'mailbox_id'   => $this->mailbox->id,
            'customer_id'  => $this->customer->id,
        ]);

        $this->actingAs($this->user)
            ->patch("/conversations/{$conversation->id}/status", ['status' => $status])
            ->assertRedirect();

        expect($conversation->fresh()->status->value)->toBe($status);
    }
});

test('changing status creates an activity thread', function () {
    Queue::fake();
    Event::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'open',
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/status", ['status' => 'closed'])
        ->assertRedirect();

    $activity = Thread::where('conversation_id', $conversation->id)
        ->where('type', 'activity')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->body)->toContain('Open');
    expect($activity->body)->toContain('Closed');
});

test('marking conversation as spam blocks the customer', function () {
    Queue::fake();
    Event::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'open',
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/status", ['status' => 'spam'])
        ->assertRedirect();

    expect($this->customer->fresh()->is_blocked)->toBeTrue();
});

test('reopening a spam conversation unblocks the customer', function () {
    Queue::fake();
    Event::fake();

    $this->customer->update(['is_blocked' => true]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'spam',
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/status", ['status' => 'open'])
        ->assertRedirect();

    expect($this->customer->fresh()->is_blocked)->toBeFalse();
});

// ── Priority validation ───────────────────────────────────────────────────────

test('updatePriority rejects invalid priority', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/priority", ['priority' => 'critical'])
        ->assertSessionHasErrors('priority');

    expect($conversation->fresh()->priority->value)->toBe('normal');
});

test('updatePriority accepts all valid priorities', function () {
    Queue::fake();
    Event::fake();

    foreach (array_column(ConversationPriority::cases(), 'value') as $priority) {
        $conversation = Conversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'mailbox_id'   => $this->mailbox->id,
            'customer_id'  => $this->customer->id,
        ]);

        $this->actingAs($this->user)
            ->patch("/conversations/{$conversation->id}/priority", ['priority' => $priority])
            ->assertRedirect();

        expect($conversation->fresh()->priority->value)->toBe($priority);
    }
});

test('changing priority creates an activity thread', function () {
    Queue::fake();
    Event::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'low',
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/priority", ['priority' => 'urgent'])
        ->assertRedirect();

    $activity = Thread::where('conversation_id', $conversation->id)
        ->where('type', 'activity')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->body)->toContain('Low');
    expect($activity->body)->toContain('Urgent');
});
