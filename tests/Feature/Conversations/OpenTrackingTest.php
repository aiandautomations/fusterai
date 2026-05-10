<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);
});

test('pixel returns a 1x1 gif', function () {
    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'tracking_token' => 'testtoken123',
    ]);

    $response = $this->get('/t/testtoken123.gif');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/gif');
});

test('pixel records opened_at on first hit', function () {
    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'tracking_token' => 'tok123',
        'opened_at' => null,
    ]);

    $this->get('/t/tok123.gif');

    expect($thread->fresh()->opened_at)->not->toBeNull();
});

test('pixel is idempotent and does not overwrite opened_at', function () {
    $firstOpen = now()->subHour();

    $thread = Thread::factory()->create([
        'conversation_id' => $this->conversation->id,
        'tracking_token' => 'idempotent',
        'opened_at' => $firstOpen,
    ]);

    $this->get('/t/idempotent.gif');

    expect($thread->fresh()->opened_at->timestamp)->toBe($firstOpen->timestamp);
});

test('pixel returns gif for unknown token without error', function () {
    $response = $this->get('/t/unknowntoken.gif');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/gif');
});
