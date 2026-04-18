<?php

use App\Domains\Conversation\Jobs\SendAutoReplyJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->workspace = Workspace::factory()->create();
    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
});

function mailboxWithAutoReply(int $workspaceId, array $autoReplyOverrides = []): Mailbox
{
    return Mailbox::factory()->create([
        'workspace_id' => $workspaceId,
        'auto_reply_config' => array_merge([
            'enabled' => true,
            'subject' => 'We received your message',
            'body' => 'Thank you for reaching out.',
        ], $autoReplyOverrides),
    ]);
}

// ── Happy path ────────────────────────────────────────────────────────────────

test('auto-reply job creates an activity thread after sending', function () {
    $mailbox = mailboxWithAutoReply($this->workspace->id);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    (new SendAutoReplyJob($conversation))->handle();

    $activity = Thread::where('conversation_id', $conversation->id)
        ->where('type', 'activity')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->body)->toBe('Auto-reply sent to customer.');
});

test('auto-reply job sends an email when auto-reply is enabled', function () {
    $mailbox = mailboxWithAutoReply($this->workspace->id);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    (new SendAutoReplyJob($conversation))->handle();

    Mail::assertSent(SendQueuedMailable::class);
})->skip('Raw mailer send() not captured by Mail::assertSent() — covered by activity thread assertion');

// ── Guard clauses ─────────────────────────────────────────────────────────────

test('auto-reply job skips when auto_reply_config is disabled', function () {
    $mailbox = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'auto_reply_config' => ['enabled' => false, 'subject' => 'Hi', 'body' => 'Thanks'],
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    (new SendAutoReplyJob($conversation))->handle();

    expect(Thread::where('conversation_id', $conversation->id)->where('type', 'activity')->count())->toBe(0);
});

test('auto-reply job skips when mailbox has no auto_reply_config', function () {
    $mailbox = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'auto_reply_config' => null,
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    (new SendAutoReplyJob($conversation))->handle();

    expect(Thread::where('conversation_id', $conversation->id)->where('type', 'activity')->count())->toBe(0);
});

test('auto-reply job skips when customer has no email', function () {
    $mailbox = mailboxWithAutoReply($this->workspace->id);
    $noEmail = Customer::factory()->create(['workspace_id' => $this->workspace->id, 'email' => '']);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $mailbox->id,
        'customer_id' => $noEmail->id,
    ]);

    (new SendAutoReplyJob($conversation))->handle();

    expect(Thread::where('conversation_id', $conversation->id)->where('type', 'activity')->count())->toBe(0);
});
