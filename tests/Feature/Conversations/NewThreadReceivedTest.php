<?php

use App\Domains\Conversation\Models\Attachment;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Events\NewThreadReceived;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace    = Workspace::factory()->create();
    $this->mailbox      = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer     = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);
});

test('NewThreadReceived broadcast payload includes attachments', function () {
    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'customer_id'     => $this->customer->id,
        'type'            => 'message',
        'source'          => 'email',
    ]);

    // Create an attachment for this thread
    $attachment = Attachment::create([
        'thread_id' => $thread->id,
        'filename'  => 'invoice.pdf',
        'path'      => 'attachments/1/invoice.pdf',
        'mime_type' => 'application/pdf',
        'size'      => 12345,
    ]);

    $thread->load(['user', 'customer', 'attachments']);

    $event   = new NewThreadReceived($thread);
    $payload = $event->broadcastWith();

    expect($payload['thread']['attachments'])->toHaveCount(1);
    expect($payload['thread']['attachments'][0])->toHaveKeys(['id', 'filename', 'mime_type', 'size', 'url']);
    expect($payload['thread']['attachments'][0]['filename'])->toBe('invoice.pdf');
    expect($payload['thread']['attachments'][0]['mime_type'])->toBe('application/pdf');
    expect($payload['thread']['attachments'][0]['size'])->toBe(12345);
});

test('NewThreadReceived broadcast payload includes empty attachments array when none exist', function () {
    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'customer_id'     => $this->customer->id,
        'type'            => 'message',
        'source'          => 'email',
    ]);

    $thread->load(['user', 'customer', 'attachments']);

    $event   = new NewThreadReceived($thread);
    $payload = $event->broadcastWith();

    expect($payload['thread']['attachments'])->toBe([]);
});

test('NewThreadReceived broadcast payload includes core thread fields', function () {
    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'customer_id'     => $this->customer->id,
        'type'            => 'message',
        'source'          => 'email',
    ]);

    $thread->load(['user', 'customer', 'attachments']);

    $event   = new NewThreadReceived($thread);
    $payload = $event->broadcastWith();

    expect($payload['thread'])->toHaveKeys([
        'id', 'conversation_id', 'user_id', 'customer_id',
        'type', 'body', 'source', 'created_at',
        'user', 'customer', 'attachments',
    ]);
});

test('NewThreadReceived broadcasts on the correct private channel', function () {
    $thread   = Thread::factory()->create(['conversation_id' => $this->conversation->id]);
    $event    = new NewThreadReceived($thread);
    $channels = $event->broadcastOn();

    // PrivateChannel prefixes the stored name with 'private-'
    $channelNames = array_map(fn ($ch) => $ch->name, $channels);

    expect($channelNames)->toContain("private-conversation.{$this->conversation->id}");
});

test('NewThreadReceived also broadcasts on public livechat channel for chat source', function () {
    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'source'          => 'chat',
    ]);

    $event    = new NewThreadReceived($thread);
    $channels = $event->broadcastOn();

    $channelNames = array_map(fn ($ch) => $ch->name, $channels);

    expect($channelNames)->toContain("livechat.{$this->conversation->id}");
    expect(count($channels))->toBe(2);
});
