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
