<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user      = User::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer  = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('conversations index requires auth', function () {
    $this->get('/conversations')->assertRedirect('/login');
});

test('agent can view conversations list', function () {
    Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $this->actingAs($this->user)
        ->get('/conversations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Conversations/Index'));
});

test('agent can view a conversation', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $this->actingAs($this->user)
        ->get("/conversations/{$conversation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Conversations/Show'));
});

test('agent cannot view conversation from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $conversation   = Conversation::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $otherWorkspace->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $otherWorkspace->id])->id,
    ]);

    $this->actingAs($this->user)
        ->get("/conversations/{$conversation->id}")
        ->assertForbidden();
});

test('agent can update conversation status', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'status'       => 'open',
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/status", ['status' => 'closed'])
        ->assertRedirect();

    expect($conversation->fresh()->status)->toBe('closed');
});

test('agent can update conversation priority', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/priority", ['priority' => 'urgent'])
        ->assertRedirect();

    expect($conversation->fresh()->priority)->toBe('urgent');
});

test('agent can assign conversation to themselves', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$conversation->id}/assign", ['user_id' => $this->user->id])
        ->assertRedirect();

    expect($conversation->fresh()->assigned_user_id)->toBe($this->user->id);
});
