<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Hooks;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Modules\SlaManager\Jobs\CheckSlaBreachJob;
use Modules\SlaManager\Models\SlaPolicy;
use Modules\SlaManager\Models\SlaStatus;
use Modules\SlaManager\Notifications\SlaBreachedNotification;
use Modules\SlaManager\Providers\SlaManagerServiceProvider;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer  = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->agent     = User::factory()->create([
        'workspace_id' => $this->workspace->id,
        'role'         => 'agent',
    ]);

    // Boot the service provider so hooks are registered
    app()->register(SlaManagerServiceProvider::class);

    // Create an active SLA policy for normal priority
    $this->policy = SlaPolicy::create([
        'workspace_id'           => $this->workspace->id,
        'name'                   => 'Normal Priority SLA',
        'priority'               => 'normal',
        'first_response_minutes' => 60,
        'resolution_minutes'     => 480,
        'active'                 => true,
    ]);
});

afterEach(function () {
    Hooks::reset();
});

// ── SLA attachment ────────────────────────────────────────────────────────────

test('SLA status is created when a conversation is created', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
    ]);

    Hooks::doAction('conversation.created', $conversation);

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status)->not->toBeNull();
    expect($status->sla_policy_id)->toBe($this->policy->id);
    expect((int) $conversation->created_at->diffInMinutes($status->first_response_due_at, false))->toBe(60);
    expect((int) $conversation->created_at->diffInMinutes($status->resolution_due_at, false))->toBe(480);
});

test('SLA is not attached when no policy matches the priority', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'urgent', // no policy for urgent
    ]);

    Hooks::doAction('conversation.created', $conversation);

    expect(SlaStatus::where('conversation_id', $conversation->id)->exists())->toBeFalse();
});

// ── First response tracking ───────────────────────────────────────────────────

test('first_response_achieved_at is set when agent sends a reply thread', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
    ]);

    Hooks::doAction('conversation.created', $conversation);

    $thread = $conversation->threads()->create([
        'user_id' => $this->agent->id,
        'type'    => 'message',
        'body'    => 'Hello!',
        'source'  => 'web',
    ]);

    Hooks::doAction('thread.created', $thread);

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status->first_response_achieved_at)->not->toBeNull();
    expect($status->first_response_status)->toBe('achieved');
});

test('customer reply thread does not mark first response achieved', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
    ]);

    Hooks::doAction('conversation.created', $conversation);

    // Customer reply — no user_id
    $thread = $conversation->threads()->create([
        'customer_id' => $this->customer->id,
        'type'        => 'message',
        'body'        => 'Is anyone there?',
        'source'      => 'email',
    ]);

    Hooks::doAction('thread.created', $thread);

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status->first_response_achieved_at)->toBeNull();
    expect($status->first_response_status)->toBe('pending');
});

// ── Pause / Resume ────────────────────────────────────────────────────────────

test('SLA clock pauses when conversation is set to pending', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
    ]);

    Hooks::doAction('conversation.created', $conversation);

    $conversation->status = 'pending';
    $conversation->syncChanges(); // simulate wasChanged('status')

    Hooks::doAction('conversation.updated', $conversation);

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status->isPaused())->toBeTrue();
    expect($status->first_response_status)->toBe('paused');
    expect($status->resolution_status)->toBe('paused');
});

test('SLA clock resumes and extends due dates when conversation reopens', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
    ]);

    Hooks::doAction('conversation.created', $conversation);

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    $originalFrDue  = $status->first_response_due_at->copy();
    $originalResDue = $status->resolution_due_at->copy();

    // Simulate conversation going pending, which pauses SLA
    $conversation->update(['status' => 'pending']);
    $status->pause();
    $this->travel(30)->minutes(); // simulate 30 minutes passing

    // Resume via hook — status changes from 'pending' → 'open'
    $conversation->status = 'open';
    $conversation->syncChanges();
    Hooks::doAction('conversation.updated', $conversation);

    $status->refresh();
    expect($status->isPaused())->toBeFalse();
    expect($status->pause_offset_minutes)->toBeGreaterThanOrEqual(30);
    expect($status->first_response_due_at->gt($originalFrDue))->toBeTrue();
    expect($status->resolution_due_at->gt($originalResDue))->toBeTrue();
});

// ── Resolution tracking ───────────────────────────────────────────────────────

test('resolved_at is set when conversation is closed', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
    ]);

    Hooks::doAction('conversation.created', $conversation);

    $conversation->status = 'closed';
    $conversation->syncChanges();
    Hooks::doAction('conversation.updated', $conversation);

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status->resolved_at)->not->toBeNull();
    expect($status->resolution_status)->toBe('achieved');
});

// ── Breach detection ──────────────────────────────────────────────────────────

test('CheckSlaBreachJob marks first_response_breached and notifies agent', function () {
    Notification::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id'      => $this->workspace->id,
        'mailbox_id'        => $this->mailbox->id,
        'customer_id'       => $this->customer->id,
        'priority'          => 'normal',
        'assigned_user_id'  => $this->agent->id,
    ]);

    // Create an already-overdue SLA status
    SlaStatus::create([
        'conversation_id'       => $conversation->id,
        'sla_policy_id'         => $this->policy->id,
        'first_response_due_at' => now()->subHour(),
        'resolution_due_at'     => now()->addHours(7),
    ]);

    (new CheckSlaBreachJob)->handle();

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status->first_response_breached)->toBeTrue();

    Notification::assertSentTo($this->agent, SlaBreachedNotification::class, function ($n) {
        return $n->breachType === 'first_response';
    });

    // Check activity thread was created
    expect($conversation->fresh()->threads()->where('type', 'activity')->exists())->toBeTrue();
});

test('CheckSlaBreachJob marks resolution_breached', function () {
    Notification::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id'      => $this->workspace->id,
        'mailbox_id'        => $this->mailbox->id,
        'customer_id'       => $this->customer->id,
        'priority'          => 'normal',
        'assigned_user_id'  => $this->agent->id,
    ]);

    SlaStatus::create([
        'conversation_id'           => $conversation->id,
        'sla_policy_id'             => $this->policy->id,
        'first_response_due_at'     => now()->subHours(8),
        'resolution_due_at'         => now()->subHour(),
        'first_response_achieved_at' => now()->subHours(7),
    ]);

    (new CheckSlaBreachJob)->handle();

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status->resolution_breached)->toBeTrue();

    Notification::assertSentTo($this->agent, SlaBreachedNotification::class, function ($n) {
        return $n->breachType === 'resolution';
    });
});

test('CheckSlaBreachJob skips paused conversations', function () {
    Notification::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
    ]);

    SlaStatus::create([
        'conversation_id'       => $conversation->id,
        'sla_policy_id'         => $this->policy->id,
        'first_response_due_at' => now()->subHour(),
        'resolution_due_at'     => now()->subMinutes(10),
        'paused_at'             => now()->subHour(), // paused
    ]);

    (new CheckSlaBreachJob)->handle();

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status->first_response_breached)->toBeFalse();
    expect($status->resolution_breached)->toBeFalse();

    Notification::assertNothingSent();
});

test('CheckSlaBreachJob skips closed conversations', function () {
    Notification::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
        'status'       => 'closed',
    ]);

    SlaStatus::create([
        'conversation_id'       => $conversation->id,
        'sla_policy_id'         => $this->policy->id,
        'first_response_due_at' => now()->subHour(),
        'resolution_due_at'     => now()->subMinutes(10),
    ]);

    (new CheckSlaBreachJob)->handle();

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status->first_response_breached)->toBeFalse();
});

// ── Priority re-attach ────────────────────────────────────────────────────────

test('re-attaching SLA on priority change does not overwrite first_response_achieved_at', function () {
    // Create urgent policy
    SlaPolicy::create([
        'workspace_id'           => $this->workspace->id,
        'name'                   => 'Urgent SLA',
        'priority'               => 'urgent',
        'first_response_minutes' => 15,
        'resolution_minutes'     => 60,
        'active'                 => true,
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'priority'     => 'normal',
    ]);

    Hooks::doAction('conversation.created', $conversation);

    // Mark first response achieved
    $achievedAt = now()->subMinutes(5);
    SlaStatus::where('conversation_id', $conversation->id)
        ->update(['first_response_achieved_at' => $achievedAt]);

    // Change priority to urgent and trigger re-attach
    $conversation->priority = 'urgent';
    $conversation->syncChanges();
    Hooks::doAction('conversation.updated', $conversation);

    $status = SlaStatus::where('conversation_id', $conversation->id)->first();
    expect($status->first_response_achieved_at)->not->toBeNull();
    expect($status->first_response_status)->toBe('achieved');
});
