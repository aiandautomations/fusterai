<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();

    $this->workspace = Workspace::factory()->create();
    $this->agent = agentUser($this->workspace);
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->conv = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'starred' => false,
    ]);
});

// ── Toggle star ───────────────────────────────────────────────────────────────

test('agent can star an unstarred conversation', function () {
    $this->actingAs($this->agent)
        ->postJson("/conversations/{$this->conv->id}/star")
        ->assertOk()
        ->assertJson(['starred' => true]);

    expect($this->conv->fresh()->starred)->toBeTrue();
});

test('agent can unstar a starred conversation', function () {
    $this->conv->update(['starred' => true]);

    $this->actingAs($this->agent)
        ->postJson("/conversations/{$this->conv->id}/star")
        ->assertOk()
        ->assertJson(['starred' => false]);

    expect($this->conv->fresh()->starred)->toBeFalse();
});

test('cannot star conversation in another workspace', function () {
    $other = Workspace::factory()->create();
    $otherConv = Conversation::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->agent)
        ->postJson("/conversations/{$otherConv->id}/star")
        ->assertForbidden();
});

test('unauthenticated user cannot star a conversation', function () {
    $this->postJson("/conversations/{$this->conv->id}/star")
        ->assertUnauthorized();
});

// ── Starred filter ────────────────────────────────────────────────────────────

test('starred filter returns only starred conversations', function () {
    $starred = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'starred' => true,
        'status' => 'open',
    ]);

    $this->actingAs($this->agent)
        ->get('/conversations?starred=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('conversations.data', 1)
            ->where('conversations.data.0.id', $starred->id)
        );
});

test('starred count is included in conversation counts', function () {
    Conversation::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'starred' => true,
        'status' => 'open',
    ]);

    $response = $this->actingAs($this->agent)
        ->get('/conversations')
        ->assertOk();

    $response->assertInertia(fn ($page) => $page->where('counts.starred', 3));
});
