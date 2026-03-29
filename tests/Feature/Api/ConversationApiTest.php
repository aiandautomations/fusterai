<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Mailbox\Models\Mailbox;
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
