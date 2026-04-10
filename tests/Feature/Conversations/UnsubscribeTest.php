<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Conversation\Jobs\ProcessBounceJob;
use App\Domains\Conversation\Jobs\SendReplyJob;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer  = Customer::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email'        => 'customer@example.com',
        'name'         => 'Test Customer',
        'is_blocked'   => false,
    ]);
});

// ── Unsubscribe page ──────────────────────────────────────────────────────────

test('GET unsubscribe with valid signed URL shows confirmation page', function () {
    $url = URL::signedRoute('unsubscribe', ['customer' => $this->customer->id]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Unsubscribe')
        ->assertSee($this->customer->email);
});

test('GET unsubscribe with invalid signature returns 403', function () {
    $this->get('/unsubscribe/' . $this->customer->id)
        ->assertForbidden();
});

test('DELETE unsubscribe with valid signed URL marks customer as blocked', function () {
    $url = URL::signedRoute('unsubscribe', ['customer' => $this->customer->id]);

    $this->delete($url)->assertOk()->assertSee('unsubscribed');

    expect($this->customer->fresh()->is_blocked)->toBeTrue();
});

test('DELETE unsubscribe with invalid signature returns 403', function () {
    $this->delete('/unsubscribe/' . $this->customer->id)
        ->assertForbidden();
});

test('DELETE unsubscribe is idempotent when already blocked', function () {
    $this->customer->update(['is_blocked' => true]);
    $url = URL::signedRoute('unsubscribe', ['customer' => $this->customer->id]);

    $this->delete($url)->assertOk();

    expect($this->customer->fresh()->is_blocked)->toBeTrue();
});

// ── List-Unsubscribe header in SendReplyJob ───────────────────────────────────

test('SendReplyJob stores outbound_message_id in thread meta', function () {
    Queue::fake();
    \Illuminate\Support\Facades\Mail::fake();

    $agent        = User::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'channel_type' => 'email',
    ]);

    $thread = Thread::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id'         => $agent->id,
        'customer_id'     => null,
        'type'            => 'message',
        'source'          => 'web',
    ]);

    // Run the job (mail goes to log driver in test env since no smtp_config)
    (new SendReplyJob($thread, $conversation->load(['mailbox', 'customer', 'threads'])))->handle();

    // Stored with angle brackets so it matches what mail servers return in bounce notifications
    expect($thread->fresh()->meta['outbound_message_id'])->toStartWith('<thread-')
        ->and($thread->fresh()->meta['outbound_message_id'])->toEndWith('@fusterai>');
});

// ── Soft bounce retry ─────────────────────────────────────────────────────────

test('ProcessBounceJob retries send for soft bounce when outbound thread found', function () {
    Queue::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $outboundMsgId = '<thread-42-abc@fusterai>';

    $thread = Thread::factory()->create([
        'conversation_id' => $conversation->id,
        'type'            => 'message',
        'meta'            => ['outbound_message_id' => $outboundMsgId],
    ]);

    (new ProcessBounceJob(
        mailboxId:         $this->mailbox->id,
        toEmail:           $this->customer->email,
        bounceType:        'soft',
        bounceMessage:     'Mailbox full',
        originalMessageId: $outboundMsgId,
    ))->handle();

    Queue::assertPushedOn('email-outbound', SendReplyJob::class);
});

test('ProcessBounceJob does NOT retry for hard bounce', function () {
    Queue::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $outboundMsgId = '<thread-99-def@fusterai>';

    Thread::factory()->create([
        'conversation_id' => $conversation->id,
        'type'            => 'message',
        'meta'            => ['outbound_message_id' => $outboundMsgId],
    ]);

    (new ProcessBounceJob(
        mailboxId:         $this->mailbox->id,
        toEmail:           $this->customer->email,
        bounceType:        'hard',
        bounceMessage:     'User unknown',
        originalMessageId: $outboundMsgId,
    ))->handle();

    Queue::assertNotPushed(SendReplyJob::class);
    expect($conversation->fresh()->status->value)->toBe('pending');
});

test('ProcessBounceJob hard bounce moves conversation to pending', function () {
    Queue::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'open',
    ]);

    $outboundMsgId = '<thread-77-ghi@fusterai>';

    Thread::factory()->create([
        'conversation_id' => $conversation->id,
        'type'            => 'message',
        'meta'            => ['outbound_message_id' => $outboundMsgId],
    ]);

    (new ProcessBounceJob(
        mailboxId:         $this->mailbox->id,
        toEmail:           $this->customer->email,
        bounceType:        'hard',
        bounceMessage:     'No such user',
        originalMessageId: $outboundMsgId,
    ))->handle();

    expect($conversation->fresh()->status->value)->toBe('pending');
});

test('ProcessBounceJob logs activity thread on any bounce', function () {
    Queue::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $outboundMsgId = '<thread-55-jkl@fusterai>';

    Thread::factory()->create([
        'conversation_id' => $conversation->id,
        'type'            => 'message',
        'meta'            => ['outbound_message_id' => $outboundMsgId],
    ]);

    (new ProcessBounceJob(
        mailboxId:         $this->mailbox->id,
        toEmail:           $this->customer->email,
        bounceType:        'soft',
        bounceMessage:     'Service unavailable',
        originalMessageId: $outboundMsgId,
    ))->handle();

    $activity = Thread::where('conversation_id', $conversation->id)
        ->where('type', 'activity')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->body)->toContain('soft');
    expect($activity->body)->toContain('Service unavailable');
});

test('ProcessBounceJob is a no-op when no matching conversation exists', function () {
    Queue::fake();

    // Should not throw
    (new ProcessBounceJob(
        mailboxId:         $this->mailbox->id,
        toEmail:           'nobody@example.com',
        bounceType:        'hard',
        bounceMessage:     'Unknown',
        originalMessageId: '<does-not-exist@fusterai>',
    ))->handle();

    Queue::assertNotPushed(SendReplyJob::class);
});
