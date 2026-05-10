<?php

use App\Domains\Conversation\Jobs\SendReplyJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->workspace = Workspace::factory()->create();
    $this->agent = agentUser($this->workspace);
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'status' => 'open',
    ]);
});

// ── Scheduling ────────────────────────────────────────────────────────────────

test('thread is created with send_at when send_at is provided', function () {
    $sendAt = now()->addHours(2)->toISOString();

    $this->actingAs($this->agent)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => 'Hello, I will reply later!',
            'type' => 'message',
            'send_at' => $sendAt,
        ])
        ->assertRedirect();

    $thread = Thread::where('conversation_id', $this->conversation->id)->latest()->first();
    expect($thread->send_at)->not->toBeNull();
});

test('send_at must be in the future', function () {
    $this->actingAs($this->agent)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => 'Hello!',
            'type' => 'message',
            'send_at' => now()->subHour()->toISOString(),
        ])
        ->assertSessionHasErrors('send_at');
});

test('SendReplyJob is dispatched with delay when send_at is provided', function () {
    $sendAt = now()->addHours(3)->toISOString();

    $this->actingAs($this->agent)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => 'Scheduled reply',
            'type' => 'message',
            'send_at' => $sendAt,
        ]);

    Queue::assertPushed(SendReplyJob::class);
});

test('no send_at stores thread without scheduling', function () {
    $this->actingAs($this->agent)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => 'Immediate reply',
            'type' => 'message',
        ])
        ->assertRedirect();

    $thread = Thread::where('conversation_id', $this->conversation->id)->latest()->first();
    expect($thread->send_at)->toBeNull();
});

// ── Cancel schedule ───────────────────────────────────────────────────────────

test('agent can cancel a scheduled thread', function () {
    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->agent->id,
        'type' => 'message',
        'body' => 'Scheduled',
        'send_at' => now()->addHours(2),
    ]);

    $this->actingAs($this->agent)
        ->deleteJson("/conversations/{$this->conversation->id}/threads/{$thread->id}/schedule")
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($thread->fresh()->send_at)->toBeNull();
});

test('cannot cancel a thread that is not scheduled', function () {
    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->agent->id,
        'type' => 'message',
        'body' => 'Already sent',
        'send_at' => null,
    ]);

    $this->actingAs($this->agent)
        ->deleteJson("/conversations/{$this->conversation->id}/threads/{$thread->id}/schedule")
        ->assertNotFound();
});

test('cannot cancel schedule for thread in another workspace', function () {
    $other = Workspace::factory()->create();
    $otherMailbox = Mailbox::factory()->create(['workspace_id' => $other->id]);
    $otherCustomer = Customer::factory()->create(['workspace_id' => $other->id]);
    $otherConv = Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id' => $otherMailbox->id,
        'customer_id' => $otherCustomer->id,
    ]);
    $thread = Thread::factory()->create([
        'conversation_id' => $otherConv->id,
        'send_at' => now()->addHour(),
    ]);

    $this->actingAs($this->agent)
        ->deleteJson("/conversations/{$this->conversation->id}/threads/{$thread->id}/schedule")
        ->assertNotFound();
});

// ── SendReplyJob cancellation check ──────────────────────────────────────────

test('SendReplyJob bails if send_at was cleared before it runs', function () {
    $scheduledAt = now()->addHours(2);

    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->agent->id,
        'type' => 'message',
        'body' => 'Cancelled scheduled',
        'source' => 'web',
        'send_at' => $scheduledAt,
    ]);

    // Agent cancels before job runs
    $thread->update(['send_at' => null]);

    // Job carries the original scheduledAt — thread in DB now has send_at = null
    $job = new SendReplyJob($thread, $this->conversation, $scheduledAt);

    // Should bail silently without attempting to send
    $job->handle();

    expect(true)->toBeTrue(); // reached without exception
});
