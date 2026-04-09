<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Mailbox\Models\Mailbox;
use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;

beforeEach(function () {
    Event::fake();

    $this->workspace = Workspace::factory()->create();
    $this->user      = User::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/conversations')->assertUnauthorized();
});

test('authenticated user can list their workspace conversations', function () {
    Conversation::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
    ]);

    Passport::actingAs($this->user);

    $this->getJson('/api/conversations')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page']);
});

test('conversations are scoped to workspace', function () {
    $other = Workspace::factory()->create();
    Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $other->id])->id,
    ]);

    Conversation::factory()->create(['workspace_id' => $this->workspace->id, 'mailbox_id' => $this->mailbox->id]);

    Passport::actingAs($this->user);

    $response = $this->getJson('/api/conversations')->assertOk();

    expect($response->json('total'))->toBe(1);
});

test('can filter conversations by status', function () {
    Conversation::factory()->create(['workspace_id' => $this->workspace->id, 'mailbox_id' => $this->mailbox->id, 'status' => 'open']);
    Conversation::factory()->create(['workspace_id' => $this->workspace->id, 'mailbox_id' => $this->mailbox->id, 'status' => 'closed']);

    Passport::actingAs($this->user);

    $response = $this->getJson('/api/conversations?status=open')->assertOk();

    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.status'))->toBe('open');
});

test('can show a single conversation', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
    ]);

    Passport::actingAs($this->user);

    $this->getJson("/api/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJsonPath('id', $conversation->id);
});

test('cannot view conversation from another workspace', function () {
    $other        = Workspace::factory()->create();
    $conversation = Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $other->id])->id,
    ]);

    Passport::actingAs($this->user);

    $this->getJson("/api/conversations/{$conversation->id}")->assertNotFound();
});

test('can create a conversation via API', function () {
    Passport::actingAs($this->user);

    $this->postJson('/api/conversations', [
        'subject'        => 'API Test',
        'customer_email' => 'api@example.com',
        'body'           => 'Hello from API',
    ])
    ->assertCreated()
    ->assertJsonPath('subject', 'API Test');
});

test('can reply to a conversation', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
    ]);

    Passport::actingAs($this->user);

    $this->postJson("/api/conversations/{$conversation->id}/reply", [
        'body' => 'This is a reply',
    ])->assertCreated();
});

test('can update conversation status via API', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'status'       => 'open',
    ]);

    Passport::actingAs($this->user);

    $this->patchJson("/api/conversations/{$conversation->id}", ['status' => 'closed'])
        ->assertOk()
        ->assertJsonPath('status', 'closed');
});

// ── Enum validation boundaries ────────────────────────────────────────────────

test('list rejects invalid status filter', function () {
    Passport::actingAs($this->user);

    $this->getJson('/api/conversations?status=invalid')
        ->assertUnprocessable();
});

test('list rejects invalid priority filter', function () {
    Passport::actingAs($this->user);

    $this->getJson('/api/conversations?priority=critical')
        ->assertUnprocessable();
});

test('create rejects invalid status', function () {
    Passport::actingAs($this->user);

    $this->postJson('/api/conversations', [
        'subject'        => 'Test',
        'customer_email' => 'test@example.com',
        'body'           => 'Hello',
        'status'         => 'invalid',
    ])->assertUnprocessable();
});

test('create rejects invalid priority', function () {
    Passport::actingAs($this->user);

    $this->postJson('/api/conversations', [
        'subject'        => 'Test',
        'customer_email' => 'test@example.com',
        'body'           => 'Hello',
        'priority'       => 'critical',
    ])->assertUnprocessable();
});

test('update rejects invalid status', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'status'       => 'open',
    ]);

    Passport::actingAs($this->user);

    $this->patchJson("/api/conversations/{$conversation->id}", ['status' => 'invalid'])
        ->assertUnprocessable();

    expect($conversation->fresh()->status->value)->toBe('open');
});

test('update rejects invalid priority', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'priority'     => 'normal',
    ]);

    Passport::actingAs($this->user);

    $this->patchJson("/api/conversations/{$conversation->id}", ['priority' => 'critical'])
        ->assertUnprocessable();

    expect($conversation->fresh()->priority->value)->toBe('normal');
});

test('can filter api conversations by priority', function () {
    Conversation::factory()->create(['workspace_id' => $this->workspace->id, 'mailbox_id' => $this->mailbox->id, 'priority' => 'urgent']);
    Conversation::factory()->create(['workspace_id' => $this->workspace->id, 'mailbox_id' => $this->mailbox->id, 'priority' => 'low']);

    Passport::actingAs($this->user);

    $response = $this->getJson('/api/conversations?priority=urgent')->assertOk();
    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.priority'))->toBe('urgent');
});

test('create accepts all valid statuses', function () {
    Passport::actingAs($this->user);

    foreach (array_column(ConversationStatus::cases(), 'value') as $status) {
        $response = $this->postJson('/api/conversations', [
            'subject'        => "Test {$status}",
            'customer_email' => "{$status}@example.com",
            'body'           => 'Hello',
            'status'         => $status,
        ])->assertCreated();

        expect($response->json('status'))->toBe($status);
    }
});

test('create accepts all valid priorities', function () {
    Passport::actingAs($this->user);

    foreach (array_column(ConversationPriority::cases(), 'value') as $priority) {
        $response = $this->postJson('/api/conversations', [
            'subject'        => "Test {$priority}",
            'customer_email' => "{$priority}@example.com",
            'body'           => 'Hello',
            'priority'       => $priority,
        ])->assertCreated();

        expect($response->json('priority'))->toBe($priority);
    }
});
