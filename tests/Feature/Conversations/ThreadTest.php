<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => Mailbox::factory()->create(['workspace_id' => $this->workspace->id])->id,
        'customer_id' => Customer::factory()->create(['workspace_id' => $this->workspace->id])->id,
    ]);
});

test('agent can send a reply', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => '<p>Hello, how can I help?</p>',
            'type' => 'message',
        ])
        ->assertRedirect();

    expect(
        Thread::where('conversation_id', $this->conversation->id)->where('type', 'message')->count()
    )->toBe(1);
});

test('agent can add an internal note', function () {
    $this->actingAs($this->user)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => '<p>Internal note</p>',
            'type' => 'note',
        ])
        ->assertRedirect();

    expect(
        Thread::where('conversation_id', $this->conversation->id)->where('type', 'note')->count()
    )->toBe(1);
});

test('reply body is required', function () {
    $this->actingAs($this->user)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => '',
            'type' => 'reply',
        ])
        ->assertSessionHasErrors('body');
});

test('thread type must be message or note', function () {
    $this->actingAs($this->user)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => 'Hello',
            'type' => 'activity',   // agents cannot post activity threads directly
        ])
        ->assertSessionHasErrors('type');
});

test('thread type rejects arbitrary strings', function () {
    $this->actingAs($this->user)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => 'Hello',
            'type' => 'ai_suggestion',
        ])
        ->assertSessionHasErrors('type');
});

test('reply creates a thread with correct type', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => '<p>Test reply</p>',
            'type' => 'message',
        ])
        ->assertRedirect();

    $thread = Thread::where('conversation_id', $this->conversation->id)
        ->latest()
        ->first();

    expect($thread->type->value)->toBe('message');
    expect($thread->user_id)->toBe($this->user->id);
});

test('note creates a thread with note type', function () {
    $this->actingAs($this->user)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => '<p>Internal note</p>',
            'type' => 'note',
        ])
        ->assertRedirect();

    $thread = Thread::where('conversation_id', $this->conversation->id)
        ->where('type', 'note')
        ->latest()
        ->first();

    expect($thread)->not->toBeNull();
    expect($thread->customer_id)->toBeNull();
});
