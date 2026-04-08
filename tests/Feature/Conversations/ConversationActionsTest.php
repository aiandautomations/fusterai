<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Tag;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
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
        'priority'     => 'normal',
    ]);
});

test('agent can snooze a conversation', function () {
    $until = now()->addHours(4)->toIso8601String();

    $this->actingAs($this->user)
        ->patch("/conversations/{$this->conv->id}/snooze", ['until' => $until])
        ->assertRedirect();

    expect($this->conv->fresh()->snoozed_until)->not->toBeNull();
});

test('snooze requires a future date', function () {
    $this->actingAs($this->user)
        ->patch("/conversations/{$this->conv->id}/snooze", ['until' => now()->subHour()->toIso8601String()])
        ->assertSessionHasErrors('until');
});

test('agent can sync tags on a conversation', function () {
    $tag1 = Tag::factory()->create(['workspace_id' => $this->workspace->id]);
    $tag2 = Tag::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->post("/conversations/{$this->conv->id}/tags", ['tag_ids' => [$tag1->id, $tag2->id]])
        ->assertRedirect();

    expect($this->conv->fresh()->tags->pluck('id'))->toContain($tag1->id, $tag2->id);
});

test('agent can clear all tags from a conversation', function () {
    $tag = Tag::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->conv->tags()->sync([$tag->id]);

    $this->actingAs($this->user)
        ->post("/conversations/{$this->conv->id}/tags", ['tag_ids' => []])
        ->assertRedirect();

    expect($this->conv->fresh()->tags)->toBeEmpty();
});

test('agent can change a conversation mailbox', function () {
    $newMailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->patch("/conversations/{$this->conv->id}/mailbox", ['mailbox_id' => $newMailbox->id])
        ->assertRedirect();

    expect($this->conv->fresh()->mailbox_id)->toBe($newMailbox->id);
});

test('agent can merge a conversation into another', function () {
    $target = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
    ]);

    $this->actingAs($this->user)
        ->post("/conversations/{$this->conv->id}/merge", ['into_id' => $target->id])
        ->assertRedirect();

    expect(Conversation::find($this->conv->id))->toBeNull();
});

test('merge into_id must exist', function () {
    $this->actingAs($this->user)
        ->post("/conversations/{$this->conv->id}/merge", ['into_id' => 999999])
        ->assertSessionHasErrors('into_id');
});

test('bulk action cannot close conversations from another workspace', function () {
    $other     = \App\Models\Workspace::factory()->create();
    $otherConv = Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => \App\Domains\Mailbox\Models\Mailbox::factory()->create(['workspace_id' => $other->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $other->id])->id,
        'status'       => 'open',
    ]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'    => [$otherConv->id],
            'action' => 'close',
        ])
        ->assertOk();

    // Conversation from another workspace must remain untouched
    expect($otherConv->fresh()->status->value)->toBe('open');
});

test('bulk action only affects conversations within the same workspace', function () {
    $other     = \App\Models\Workspace::factory()->create();
    $otherConv = Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => \App\Domains\Mailbox\Models\Mailbox::factory()->create(['workspace_id' => $other->id])->id,
        'customer_id'  => Customer::factory()->create(['workspace_id' => $other->id])->id,
        'status'       => 'open',
    ]);

    $this->actingAs($this->user)
        ->postJson('/conversations/bulk', [
            'ids'    => [$this->conv->id, $otherConv->id],
            'action' => 'close',
        ])
        ->assertOk();

    expect($this->conv->fresh()->status->value)->toBe('closed');
    expect($otherConv->fresh()->status->value)->toBe('open');
});
