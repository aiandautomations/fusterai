<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Event::fake();

    $this->workspace = \App\Models\Workspace::factory()->create();
    $this->user      = agentUser($this->workspace);
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer  = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->conv1 = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'open',
        'priority'     => 'normal',
    ]);
    $this->conv2 = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'open',
        'priority'     => 'low',
    ]);
});

// ── Bulk priority ─────────────────────────────────────────────────────────────

test('agent can bulk change priority', function () {
    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'      => [$this->conv1->id, $this->conv2->id],
            'action'   => 'priority',
            'priority' => 'urgent',
        ])
        ->assertOk()
        ->assertJson(['updated' => 2]);

    expect($this->conv1->fresh()->priority->value)->toBe('urgent');
    expect($this->conv2->fresh()->priority->value)->toBe('urgent');
});

test('bulk priority requires a valid priority value', function () {
    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'      => [$this->conv1->id],
            'action'   => 'priority',
            'priority' => 'critical',  // invalid
        ])
        ->assertUnprocessable();
});

test('bulk priority cannot affect conversations from other workspaces', function () {
    $otherWorkspace = \App\Models\Workspace::factory()->create();
    $otherConv      = Conversation::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $otherWorkspace->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $otherWorkspace->id])->id,
        'priority'     => 'low',
    ]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'      => [$otherConv->id],
            'action'   => 'priority',
            'priority' => 'urgent',
        ])
        ->assertOk()
        ->assertJson(['updated' => 0]);

    expect($otherConv->fresh()->priority->value)->toBe('low');
});

// ── Bulk assign to any agent ──────────────────────────────────────────────────

test('agent can bulk assign to another agent', function () {
    $other = User::factory()->create([
        'workspace_id' => $this->workspace->id,
        'role'         => 'agent',
    ]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'         => [$this->conv1->id, $this->conv2->id],
            'action'      => 'assign',
            'assigned_to' => $other->id,
        ])
        ->assertOk()
        ->assertJson(['updated' => 2]);

    expect($this->conv1->fresh()->assigned_user_id)->toBe($other->id);
    expect($this->conv2->fresh()->assigned_user_id)->toBe($other->id);
});

test('bulk assign can unassign conversations', function () {
    $this->conv1->update(['assigned_user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'         => [$this->conv1->id],
            'action'      => 'assign',
            'assigned_to' => null,
        ])
        ->assertOk();

    expect($this->conv1->fresh()->assigned_user_id)->toBeNull();
});

test('bulk assign rejects agent from another workspace', function () {
    $outsider = User::factory()->create(['workspace_id' => \App\Models\Workspace::factory()->create()->id]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'         => [$this->conv1->id],
            'action'      => 'assign',
            'assigned_to' => $outsider->id,
        ])
        ->assertUnprocessable();
});

// ── Date range filter ─────────────────────────────────────────────────────────

test('date_from excludes older conversations', function () {
    // conv1 and conv2 from beforeEach were created "now" — only create old ones
    Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'created_at'   => now()->subDays(10),
    ]);

    // With date_from=yesterday only today's 2 conversations should appear
    $this->actingAs($this->user)
        ->get('/conversations?date_from=' . now()->subDay()->toDateString())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('conversations.total', 2));
});

test('date_to excludes recent conversations', function () {
    // conv1 and conv2 created "now" should be excluded when date_to is in the past
    $this->actingAs($this->user)
        ->get('/conversations?date_to=' . now()->subDays(1)->toDateString())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('conversations.total', 0));
});

test('date_from and date_to combined filter to a window', function () {
    Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'created_at'   => now()->subDays(5),
    ]);

    // Only the subDays(5) conversation falls in [subDays(7), subDays(3)]
    $this->actingAs($this->user)->get(
        '/conversations?date_from=' . now()->subDays(7)->toDateString() .
        '&date_to=' . now()->subDays(3)->toDateString()
    )
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('conversations.total', 1));
});

test('date_to must be after or equal to date_from', function () {
    $this->actingAs($this->user)
        ->get('/conversations?date_from=2026-04-10&date_to=2026-04-01')
        ->assertSessionHasErrors('date_to');
});

// ── Bulk activity threads ─────────────────────────────────────────────────────

test('bulk close creates an activity thread for each conversation', function () {
    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'    => [$this->conv1->id, $this->conv2->id],
            'action' => 'close',
        ])
        ->assertOk();

    foreach ([$this->conv1, $this->conv2] as $conv) {
        $activity = Thread::where('conversation_id', $conv->id)
            ->where('type', 'activity')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->body)->toContain('closed');
    }
});

test('bulk reopen creates an activity thread for each conversation', function () {
    $this->conv1->update(['status' => 'closed']);
    $this->conv2->update(['status' => 'closed']);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'    => [$this->conv1->id, $this->conv2->id],
            'action' => 'reopen',
        ])
        ->assertOk();

    foreach ([$this->conv1, $this->conv2] as $conv) {
        $activity = Thread::where('conversation_id', $conv->id)
            ->where('type', 'activity')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->body)->toContain('reopened');
    }
});

test('bulk assign creates an activity thread naming the assignee', function () {
    $other = User::factory()->create([
        'workspace_id' => $this->workspace->id,
        'role'         => 'agent',
        'name'         => 'Jane Doe',
    ]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'         => [$this->conv1->id],
            'action'      => 'assign',
            'assigned_to' => $other->id,
        ])
        ->assertOk();

    $activity = Thread::where('conversation_id', $this->conv1->id)
        ->where('type', 'activity')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->body)->toContain('Jane Doe');
});

test('bulk unassign creates an activity thread', function () {
    $this->conv1->update(['assigned_user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'         => [$this->conv1->id],
            'action'      => 'assign',
            'assigned_to' => null,
        ])
        ->assertOk();

    $activity = Thread::where('conversation_id', $this->conv1->id)
        ->where('type', 'activity')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->body)->toContain('unassigned');
});

test('bulk spam creates an activity thread', function () {
    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'    => [$this->conv1->id],
            'action' => 'spam',
        ])
        ->assertOk();

    $activity = Thread::where('conversation_id', $this->conv1->id)
        ->where('type', 'activity')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->body)->toContain('spam');
});

test('bulk priority does not create activity threads', function () {
    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'      => [$this->conv1->id],
            'action'   => 'priority',
            'priority' => 'urgent',
        ])
        ->assertOk();

    expect(
        Thread::where('conversation_id', $this->conv1->id)->where('type', 'activity')->count()
    )->toBe(0);
});
