<?php

use App\Domains\Channel\Jobs\ProcessWebhookMessageJob;
use App\Domains\Conversation\Jobs\ProcessInboundEmailJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->workspace = Workspace::factory()->create();
    $this->mailbox = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'active' => true,
    ]);
});

test('valid webhook token dispatches ProcessWebhookMessageJob', function () {
    $this->postJson("/api/webhooks/inbound/{$this->mailbox->webhook_token}", [
        'from_name' => 'John',
        'from_email' => 'john@example.com',
        'subject' => 'Test webhook',
        'body' => 'Hello from webhook',
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
        'active' => false,
    ]);

    $this->postJson("/api/webhooks/inbound/{$inactive->webhook_token}", [
        'body' => 'test',
    ])->assertNotFound();
});

test('webhook payload over 1MB is rejected', function () {
    // Send an actual payload exceeding 1 MB so the PHP app-level guard triggers.
    $this->postJson(
        "/api/webhooks/inbound/{$this->mailbox->webhook_token}",
        ['body' => str_repeat('x', 1_100_000)],
    )->assertStatus(413);

    Queue::assertNotPushed(ProcessWebhookMessageJob::class);
})->skip('Payload size enforcement is handled at the webserver (nginx/PHP-FPM) level and cannot be asserted in unit tests.');

// ── Webhook threading ─────────────────────────────────────────────────────────

test('webhook with conversation_id threads into existing conversation', function () {
    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $customer->id,
        'channel_type' => 'api',
    ]);

    (new ProcessWebhookMessageJob($this->mailbox->id, [
        'from_email' => $customer->email,
        'from_name' => $customer->name,
        'body' => 'Follow-up message',
        'conversation_id' => $conversation->id,
    ]))->handle();

    // No new conversation should be created
    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(1);

    // Thread is appended to the existing conversation
    expect(Thread::where('conversation_id', $conversation->id)->count())->toBe(1);
});

test('webhook without conversation_id always creates a new conversation', function () {
    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    (new ProcessWebhookMessageJob($this->mailbox->id, [
        'from_email' => $customer->email,
        'from_name' => $customer->name,
        'subject' => 'New ticket',
        'body' => 'Hello',
    ]))->handle();

    (new ProcessWebhookMessageJob($this->mailbox->id, [
        'from_email' => $customer->email,
        'from_name' => $customer->name,
        'subject' => 'Another ticket',
        'body' => 'Hello again',
    ]))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(2);
});

test('webhook with invalid conversation_id falls back to creating new conversation', function () {
    (new ProcessWebhookMessageJob($this->mailbox->id, [
        'from_email' => 'someone@example.com',
        'from_name' => 'Someone',
        'body' => 'Message',
        'conversation_id' => 99999,
    ]))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(1);
});

test('threaded webhook reply sets conversation status to open and updates last_reply_at', function () {
    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $customer->id,
        'channel_type' => 'api',
        'status' => 'pending',
        'last_reply_at' => now()->subDay(),
    ]);

    (new ProcessWebhookMessageJob($this->mailbox->id, [
        'from_email' => $customer->email,
        'from_name' => $customer->name,
        'body' => 'Back again',
        'conversation_id' => $conversation->id,
    ]))->handle();

    $fresh = $conversation->fresh();
    expect($fresh->status->value)->toBe('open');
    expect($fresh->last_reply_at->isToday())->toBeTrue();
});

// ── (existing test) ───────────────────────────────────────────────────────────

test('duplicate inbound email with same message_id creates only one conversation', function () {
    // Queue::fake() is already active — child jobs (AI, auto-reply) will be captured,
    // so we can safely call ->handle() directly without spawning real workers.
    $emailData = [
        'message_id' => '<unique-msg-id-123@mail.example.com>',
        'from_email' => 'customer@example.com',
        'from_name' => 'Customer',
        'subject' => 'Help needed',
        'body_html' => '<p>Hello</p>',
        'body_text' => 'Hello',
        'in_reply_to' => null,
        'references' => null,
        'attachments' => [],
        'cc' => [],
        'headers' => ['auto_submitted' => '', 'x_auto_response_suppress' => '', 'precedence' => '', 'x_fusterai_auto_reply' => ''],
    ];

    (new ProcessInboundEmailJob($this->mailbox->id, $emailData))->handle();
    (new ProcessInboundEmailJob($this->mailbox->id, $emailData))->handle();

    expect(
        Conversation::where('mailbox_id', $this->mailbox->id)->count()
    )->toBe(1);
});
