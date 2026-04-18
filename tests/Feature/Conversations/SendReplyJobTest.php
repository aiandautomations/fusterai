<?php

use App\Domains\Conversation\Jobs\SendReplyJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->agent = User::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'channel_type' => 'email',
    ]);
});

// ── resolveCcRecipients ───────────────────────────────────────────────────────

test('resolveCcRecipients returns CC from last customer thread meta', function () {
    // Customer thread with CC in meta
    Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'customer_id' => $this->customer->id,
        'user_id' => null,
        'type' => 'message',
        'meta' => ['cc' => [
            ['email' => 'cc1@example.com', 'name' => 'CC One'],
            ['email' => 'cc2@example.com', 'name' => 'CC Two'],
        ]],
    ]);

    $replyThread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->agent->id,
        'customer_id' => null,
        'type' => 'message',
    ]);

    $conversation = $this->conversation->load('threads');
    $job = new SendReplyJob($replyThread, $conversation);

    $method = new ReflectionMethod(SendReplyJob::class, 'resolveCcRecipients');
    $method->setAccessible(true);
    $cc = $method->invoke($job, $conversation);

    expect($cc)->toHaveCount(2);
    expect($cc[0]['email'])->toBe('cc1@example.com');
    expect($cc[1]['email'])->toBe('cc2@example.com');
});

test('resolveCcRecipients returns empty array when no customer threads exist', function () {
    $replyThread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->agent->id,
        'customer_id' => null,
        'type' => 'message',
    ]);

    $conversation = $this->conversation->load('threads');
    $job = new SendReplyJob($replyThread, $conversation);

    $method = new ReflectionMethod(SendReplyJob::class, 'resolveCcRecipients');
    $method->setAccessible(true);
    $cc = $method->invoke($job, $conversation);

    expect($cc)->toBe([]);
});

test('resolveCcRecipients returns empty array when customer thread has no CC meta', function () {
    Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'customer_id' => $this->customer->id,
        'user_id' => null,
        'type' => 'message',
        'meta' => ['message_id' => '<some@id>', 'in_reply_to' => '', 'from_email' => 'x@x.com'],
    ]);

    $replyThread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->agent->id,
        'customer_id' => null,
        'type' => 'message',
    ]);

    $conversation = $this->conversation->load('threads');
    $job = new SendReplyJob($replyThread, $conversation);

    $method = new ReflectionMethod(SendReplyJob::class, 'resolveCcRecipients');
    $method->setAccessible(true);
    $cc = $method->invoke($job, $conversation);

    expect($cc)->toBe([]);
});

test('resolveCcRecipients picks the most recent customer thread when multiple exist', function () {
    // Older customer thread with CC
    Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'customer_id' => $this->customer->id,
        'user_id' => null,
        'type' => 'message',
        'meta' => ['cc' => [['email' => 'old-cc@example.com', 'name' => 'Old CC']]],
        'created_at' => now()->subHour(),
    ]);

    // Newer customer thread with different CC
    Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'customer_id' => $this->customer->id,
        'user_id' => null,
        'type' => 'message',
        'meta' => ['cc' => [['email' => 'new-cc@example.com', 'name' => 'New CC']]],
        'created_at' => now(),
    ]);

    $replyThread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->agent->id,
        'customer_id' => null,
        'type' => 'message',
    ]);

    $conversation = $this->conversation->load('threads');
    $job = new SendReplyJob($replyThread, $conversation);

    $method = new ReflectionMethod(SendReplyJob::class, 'resolveCcRecipients');
    $method->setAccessible(true);
    $cc = $method->invoke($job, $conversation);

    expect($cc[0]['email'])->toBe('new-cc@example.com');
});

// ── Guard clauses ─────────────────────────────────────────────────────────────

test('reply job skips send when customer has no email', function () {
    $noEmailCustomer = Customer::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => '',
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $noEmailCustomer->id,
        'channel_type' => 'email',
    ]);

    $thread = Thread::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->agent->id,
        'type' => 'message',
    ]);

    // Should complete without throwing (early return path)
    expect(fn () => (new SendReplyJob($thread, $conversation))->handle())->not->toThrow(Exception::class);
});

// ── buildSubject ──────────────────────────────────────────────────────────────

test('buildSubject prepends Re: when subject does not already have it', function () {
    $this->conversation->update(['subject' => 'Help with billing']);

    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->agent->id,
    ]);

    $job = new SendReplyJob($thread, $this->conversation);
    $method = new ReflectionMethod(SendReplyJob::class, 'buildSubject');
    $method->setAccessible(true);

    expect($method->invoke($job, $this->conversation))->toBe('Re: Help with billing');
});

test('buildSubject does not double-prefix Re: when already present', function () {
    $this->conversation->update(['subject' => 'Re: Help with billing']);

    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->agent->id,
    ]);

    $job = new SendReplyJob($thread, $this->conversation);
    $method = new ReflectionMethod(SendReplyJob::class, 'buildSubject');
    $method->setAccessible(true);

    expect($method->invoke($job, $this->conversation))->toBe('Re: Help with billing');
});
