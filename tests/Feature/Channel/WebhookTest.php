<?php

use App\Domains\Channel\Jobs\ProcessWebhookMessageJob;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->workspace = Workspace::factory()->create();
    $this->mailbox   = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'active'       => true,
    ]);
});

test('valid webhook token dispatches ProcessWebhookMessageJob', function () {
    $this->postJson("/api/webhooks/inbound/{$this->mailbox->webhook_token}", [
        'from_name'  => 'John',
        'from_email' => 'john@example.com',
        'subject'    => 'Test webhook',
        'body'       => 'Hello from webhook',
    ])->assertOk()->assertJson(['status' => 'queued']);

    Queue::assertPushedOn('webhooks', ProcessWebhookMessageJob::class);
});

test('invalid webhook token returns 404', function () {
    $this->postJson('/api/webhooks/inbound/invalid-token', [
        'body' => 'test',
    ])->assertNotFound();
});

test('inactive mailbox webhook token returns 404', function () {
    $inactive = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'active'       => false,
    ]);

    $this->postJson("/api/webhooks/inbound/{$inactive->webhook_token}", [
        'body' => 'test',
    ])->assertNotFound();
});

test('webhook payload over 1MB is rejected', function () {
    $this->postJson(
        "/api/webhooks/inbound/{$this->mailbox->webhook_token}",
        ['body' => str_repeat('x', 100)],
        ['Content-Length' => 2_000_000],
    )->assertStatus(413);

    Queue::assertNotPushed(ProcessWebhookMessageJob::class);
});

test('duplicate inbound email with same message_id creates only one conversation', function () {
    // Queue::fake() is already active — child jobs (AI, auto-reply) will be captured,
    // so we can safely call ->handle() directly without spawning real workers.
    $emailData = [
        'message_id'  => '<unique-msg-id-123@mail.example.com>',
        'from_email'  => 'customer@example.com',
        'from_name'   => 'Customer',
        'subject'     => 'Help needed',
        'body_html'   => '<p>Hello</p>',
        'body_text'   => 'Hello',
        'in_reply_to' => null,
        'references'  => null,
        'attachments' => [],
    ];

    (new \App\Domains\Conversation\Jobs\ProcessInboundEmailJob($this->mailbox->id, $emailData))->handle();
    (new \App\Domains\Conversation\Jobs\ProcessInboundEmailJob($this->mailbox->id, $emailData))->handle();

    expect(
        \App\Domains\Conversation\Models\Conversation::where('mailbox_id', $this->mailbox->id)->count()
    )->toBe(1);
});
