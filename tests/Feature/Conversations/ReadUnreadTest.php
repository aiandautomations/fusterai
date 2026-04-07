<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\ConversationRead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Event::fake();

    $this->workspace = \App\Models\Workspace::factory()->create();
    $this->user      = agentUser($this->workspace);
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer  = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->conv = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'open',
        'last_reply_at' => now()->subMinute(),
    ]);
});

// ── Mark read ─────────────────────────────────────────────────────────────────

test('agent can mark a conversation as read', function () {
    $this->actingAs($this->user)
        ->postJson("/conversations/{$this->conv->id}/read")
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(ConversationRead::where('user_id', $this->user->id)
        ->where('conversation_id', $this->conv->id)
        ->exists()
    )->toBeTrue();
});

test('mark read is idempotent', function () {
    DB::table('conversation_reads')->insert([
        'user_id'        => $this->user->id,
        'conversation_id' => $this->conv->id,
        'last_read_at'   => now()->subHour(),
    ]);

    $this->actingAs($this->user)
        ->postJson("/conversations/{$this->conv->id}/read")
        ->assertOk();

    expect(ConversationRead::where('user_id', $this->user->id)
        ->where('conversation_id', $this->conv->id)
        ->count()
    )->toBe(1);
});

// ── Mark unread ───────────────────────────────────────────────────────────────

test('agent can mark a conversation as unread', function () {
    DB::table('conversation_reads')->insert([
        'user_id'        => $this->user->id,
        'conversation_id' => $this->conv->id,
        'last_read_at'   => now(),
    ]);

    $this->actingAs($this->user)
        ->postJson("/conversations/{$this->conv->id}/unread")
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(ConversationRead::where('user_id', $this->user->id)
        ->where('conversation_id', $this->conv->id)
        ->exists()
    )->toBeFalse();
});

// ── is_unread in list ─────────────────────────────────────────────────────────

test('conversation appears as unread when never opened', function () {
    $this->actingAs($this->user)
        ->get('/conversations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.data.0.is_unread', true)
        );
});

test('conversation appears as read after opening it', function () {
    DB::table('conversation_reads')->insert([
        'user_id'        => $this->user->id,
        'conversation_id' => $this->conv->id,
        'last_read_at'   => now()->addSecond(), // after last_reply_at
    ]);

    $this->actingAs($this->user)
        ->get('/conversations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.data.0.is_unread', false)
        );
});

test('conversation becomes unread again after new reply', function () {
    DB::table('conversation_reads')->insert([
        'user_id'        => $this->user->id,
        'conversation_id' => $this->conv->id,
        'last_read_at'   => now()->subMinutes(2), // older than last_reply_at
    ]);

    $this->actingAs($this->user)
        ->get('/conversations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.data.0.is_unread', true)
        );
});

// ── Cross-workspace security ──────────────────────────────────────────────────

test('cannot mark a conversation from another workspace as read', function () {
    $other    = \App\Models\Workspace::factory()->create();
    $otherConv = Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $other->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $other->id])->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("/conversations/{$otherConv->id}/read")
        ->assertForbidden();
});

// ── Bulk read/unread ──────────────────────────────────────────────────────────

test('bulk mark_read creates read records for all selected conversations', function () {
    $conv2 = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'last_reply_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'    => [$this->conv->id, $conv2->id],
            'action' => 'mark_read',
        ])
        ->assertOk()
        ->assertJson(['updated' => 2]);

    expect(ConversationRead::where('user_id', $this->user->id)->count())->toBe(2);
});

test('bulk mark_unread removes read records', function () {
    DB::table('conversation_reads')->insert(['user_id' => $this->user->id, 'conversation_id' => $this->conv->id, 'last_read_at' => now()]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'    => [$this->conv->id],
            'action' => 'mark_unread',
        ])
        ->assertOk();

    expect(ConversationRead::where('user_id', $this->user->id)->exists())->toBeFalse();
});
