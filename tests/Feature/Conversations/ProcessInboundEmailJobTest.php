<?php

use App\Domains\Conversation\Jobs\ProcessInboundEmailJob;
use App\Domains\Conversation\Jobs\SendAutoReplyJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->workspace = Workspace::factory()->create();
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
});

// ── Base email payload ────────────────────────────────────────────────────────

function baseEmailData(array $overrides = []): array
{
    return array_merge([
        'message_id' => '<msg-'.uniqid().'@mail.example.com>',
        'subject' => 'Help needed',
        'from_email' => 'customer@example.com',
        'from_name' => 'A Customer',
        'body_html' => '<p>Hello, I need help.</p>',
        'body_text' => 'Hello, I need help.',
        'in_reply_to' => '',
        'references' => '',
        'attachments' => [],
        'cc' => [],
        'headers' => [
            'auto_submitted' => '',
            'x_auto_response_suppress' => '',
            'precedence' => '',
            'x_fusterai_auto_reply' => '',
        ],
    ], $overrides);
}

// ── Normal processing ─────────────────────────────────────────────────────────

test('normal email creates a conversation and thread', function () {
    $data = baseEmailData(['from_email' => $this->customer->email]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(1);
    expect(Thread::whereHas('conversation', fn ($q) => $q->where('mailbox_id', $this->mailbox->id))->count())->toBe(1);
});

// ── OOO / auto-reply loop prevention ─────────────────────────────────────────

test('email with Auto-Submitted auto-replied header is silently dropped', function () {
    $data = baseEmailData([
        'headers' => [
            'auto_submitted' => 'auto-replied',
            'x_auto_response_suppress' => '',
            'precedence' => '',
            'x_fusterai_auto_reply' => '',
        ],
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(0);
});

test('email with Auto-Submitted auto-generated header is silently dropped', function () {
    $data = baseEmailData([
        'headers' => [
            'auto_submitted' => 'auto-generated',
            'x_auto_response_suppress' => '',
            'precedence' => '',
            'x_fusterai_auto_reply' => '',
        ],
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(0);
});

test('email with X-FusterAI-AutoReply header is silently dropped', function () {
    $data = baseEmailData([
        'headers' => [
            'auto_submitted' => '',
            'x_auto_response_suppress' => '',
            'precedence' => '',
            'x_fusterai_auto_reply' => '1',
        ],
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(0);
});

test('email with X-Auto-Response-Suppress header is silently dropped', function () {
    $data = baseEmailData([
        'headers' => [
            'auto_submitted' => '',
            'x_auto_response_suppress' => 'All',
            'precedence' => '',
            'x_fusterai_auto_reply' => '',
        ],
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(0);
});

test('email with Precedence bulk header is silently dropped', function () {
    $data = baseEmailData([
        'headers' => [
            'auto_submitted' => '',
            'x_auto_response_suppress' => '',
            'precedence' => 'bulk',
            'x_fusterai_auto_reply' => '',
        ],
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(0);
});

test('email with Precedence junk header is silently dropped', function () {
    $data = baseEmailData([
        'headers' => [
            'auto_submitted' => '',
            'x_auto_response_suppress' => '',
            'precedence' => 'junk',
            'x_fusterai_auto_reply' => '',
        ],
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(0);
});

test('email with out-of-office subject is silently dropped', function () {
    $data = baseEmailData(['subject' => 'Out of Office: Re: Your ticket']);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(0);
});

test('email with automatic reply subject is silently dropped', function () {
    $data = baseEmailData(['subject' => 'Automatic reply: We received your message']);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(0);
});

test('email with Auto-Submitted no is treated as normal', function () {
    $data = baseEmailData([
        'from_email' => $this->customer->email,
        'headers' => [
            'auto_submitted' => 'no',
            'x_auto_response_suppress' => '',
            'precedence' => '',
            'x_fusterai_auto_reply' => '',
        ],
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(1);
});

test('dropped auto-reply does not dispatch SendAutoReplyJob', function () {
    $data = baseEmailData([
        'headers' => [
            'auto_submitted' => 'auto-replied',
            'x_auto_response_suppress' => '',
            'precedence' => '',
            'x_fusterai_auto_reply' => '',
        ],
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    Queue::assertNotPushed(SendAutoReplyJob::class);
});

// ── CC storage ────────────────────────────────────────────────────────────────

test('CC recipients are stored in thread meta', function () {
    $data = baseEmailData([
        'from_email' => $this->customer->email,
        'cc' => [
            ['email' => 'cc1@example.com', 'name' => 'CC One'],
            ['email' => 'cc2@example.com', 'name' => 'CC Two'],
        ],
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    $thread = Thread::whereHas('conversation', fn ($q) => $q->where('mailbox_id', $this->mailbox->id))
        ->latest()
        ->first();

    expect($thread->meta['cc'])->toHaveCount(2);
    expect($thread->meta['cc'][0]['email'])->toBe('cc1@example.com');
    expect($thread->meta['cc'][1]['email'])->toBe('cc2@example.com');
});

test('thread meta CC is empty array when no CC recipients', function () {
    $data = baseEmailData(['from_email' => $this->customer->email, 'cc' => []]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    $thread = Thread::whereHas('conversation', fn ($q) => $q->where('mailbox_id', $this->mailbox->id))
        ->latest()
        ->first();

    expect($thread->meta['cc'])->toBe([]);
});

// ── Subject normalization ─────────────────────────────────────────────────────

test('Fwd: prefix is stripped from subject when creating a new conversation', function () {
    $data = baseEmailData([
        'from_email' => $this->customer->email,
        'subject' => 'Fwd: Help with billing',
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->value('subject'))->toBe('Help with billing');
});

test('Fw: prefix is stripped from subject', function () {
    $data = baseEmailData([
        'from_email' => $this->customer->email,
        'subject' => 'Fw: Invoice question',
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->value('subject'))->toBe('Invoice question');
});

test('multiple forward prefixes are all stripped', function () {
    $data = baseEmailData([
        'from_email' => $this->customer->email,
        'subject' => 'Fwd: Fwd: Re: Help needed',
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->value('subject'))->toBe('Help needed');
});

test('subject without prefix is kept as-is', function () {
    $data = baseEmailData([
        'from_email' => $this->customer->email,
        'subject' => 'My account is broken',
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->value('subject'))->toBe('My account is broken');
});

// ── Thread matching ───────────────────────────────────────────────────────────

test('reply email is appended to existing conversation via In-Reply-To', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    $data = baseEmailData([
        'from_email' => $this->customer->email,
        'in_reply_to' => '<conversation-'.$conversation->id.'@fusterai>',
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(1);
    expect($conversation->fresh()->threads()->count())->toBe(1);
});

test('email with unknown In-Reply-To creates a new conversation', function () {
    $data = baseEmailData([
        'from_email' => $this->customer->email,
        'in_reply_to' => '<unknown-message-id@external.com>',
    ]);

    (new ProcessInboundEmailJob($this->mailbox->id, $data))->handle();

    expect(Conversation::where('mailbox_id', $this->mailbox->id)->count())->toBe(1);
});
