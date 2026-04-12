<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user      = agentUser($this->workspace);
});

test('dashboard requires authentication', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated user can view dashboard', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Dashboard/Index'));
});

test('dashboard response contains expected keys', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->has('stats.open')
            ->has('stats.pending')
            ->has('stats.mine')
            ->has('stats.unassigned')
            ->has('stats.trend')
            ->has('topAgents')
            ->has('recent')
        );
});

test('dashboard stats are scoped to the user workspace', function () {
    $mailbox  = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    Conversation::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $mailbox->id,
        'customer_id'  => $customer->id,
        'status'       => 'open',
    ]);

    // Create a conversation in another workspace — should not appear in stats
    $other        = Workspace::factory()->create();
    $otherMailbox = Mailbox::factory()->create(['workspace_id' => $other->id]);
    $otherCustomer = Customer::factory()->create(['workspace_id' => $other->id]);
    Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => $otherMailbox->id,
        'customer_id'  => $otherCustomer->id,
        'status'       => 'open',
    ]);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('stats.open', 3));
});
