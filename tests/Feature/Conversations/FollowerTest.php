<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Event::fake();

    $this->workspace = Workspace::factory()->create();
    $this->user = agentUser($this->workspace);
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->conv = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);
});

test('agent can follow a conversation', function () {
    $this->actingAs($this->user)
        ->post("/conversations/{$this->conv->id}/follow")
        ->assertRedirect();

    expect($this->conv->followers()->where('users.id', $this->user->id)->exists())->toBeTrue();
});

test('following a conversation is idempotent', function () {
    $this->actingAs($this->user)
        ->post("/conversations/{$this->conv->id}/follow");

    $this->actingAs($this->user)
        ->post("/conversations/{$this->conv->id}/follow");

    expect($this->conv->followers()->where('users.id', $this->user->id)->count())->toBe(1);
});

test('agent can unfollow a conversation', function () {
    $this->conv->followers()->attach($this->user->id);

    $this->actingAs($this->user)
        ->delete("/conversations/{$this->conv->id}/follow")
        ->assertRedirect();

    expect($this->conv->followers()->where('users.id', $this->user->id)->exists())->toBeFalse();
});

test('unfollowing a conversation not followed is a no-op', function () {
    $this->actingAs($this->user)
        ->delete("/conversations/{$this->conv->id}/follow")
        ->assertRedirect();

    expect($this->conv->followers()->count())->toBe(0);
});

test('cannot follow a conversation from another workspace', function () {
    $other = Workspace::factory()->create();
    $conv = Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id' => Mailbox::factory()->create(['workspace_id' => $other->id])->id,
        'customer_id' => Customer::factory()->create(['workspace_id' => $other->id])->id,
    ]);

    $this->actingAs($this->user)
        ->post("/conversations/{$conv->id}/follow")
        ->assertForbidden();
});
