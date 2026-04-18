<?php

use App\Domains\Conversation\Jobs\ProcessBounceJob;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->workspace = Workspace::factory()->create();
    $this->mailbox = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'webhook_token' => 'test-bounce-token',
        'active' => true,
    ]);
});

test('Postmark bounce payload dispatches ProcessBounceJob', function () {
    $this->postJson('/api/webhooks/bounce/test-bounce-token', [
        'Type' => 'HardBounce',
        'Recipient' => 'user@example.com',
        'MessageID' => 'msg-123',
        'Description' => 'User unknown',
    ])->assertOk()->assertJson(['status' => 'queued']);

    Queue::assertPushed(ProcessBounceJob::class);
});

test('Mailgun bounce payload dispatches ProcessBounceJob', function () {
    $this->postJson('/api/webhooks/bounce/test-bounce-token', [
        'event' => 'failed',
        'recipient' => 'user@mailgun.com',
        'error' => 'Mailbox full',
    ])->assertOk();

    Queue::assertPushed(ProcessBounceJob::class);
});

test('SES bounce payload dispatches ProcessBounceJob', function () {
    $this->postJson('/api/webhooks/bounce/test-bounce-token', [
        'mail' => ['messageId' => 'ses-abc', 'destination' => ['ses@example.com']],
        'bounce' => [
            'bounceType' => 'Permanent',
            'bouncedRecipients' => [['diagnosticCode' => '550 No such user']],
        ],
    ])->assertOk();

    Queue::assertPushed(ProcessBounceJob::class);
});

test('invalid webhook token returns 404', function () {
    $this->postJson('/api/webhooks/bounce/invalid-token', [
        'Type' => 'HardBounce',
        'Recipient' => 'user@example.com',
    ])->assertNotFound();

    Queue::assertNothingPushed();
});

test('inactive mailbox token returns 404', function () {
    $this->mailbox->update(['active' => false]);

    $this->postJson('/api/webhooks/bounce/test-bounce-token', [
        'Type' => 'HardBounce',
        'Recipient' => 'user@example.com',
    ])->assertNotFound();

    Queue::assertNothingPushed();
});
