<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->workspace    = Workspace::factory()->create();
    $this->user         = User::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $this->workspace->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $this->workspace->id])->id,
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
