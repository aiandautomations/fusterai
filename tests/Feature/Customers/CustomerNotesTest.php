<?php

use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;

beforeEach(function () {
    $this->workspace = \App\Models\Workspace::factory()->create();
    $this->user      = agentUser($this->workspace);
    $this->customer  = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('agent can save notes on a customer', function () {
    $this->actingAs($this->user)
        ->patchJson("/customers/{$this->customer->id}", ['notes' => 'VIP customer, handle with care.'])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($this->customer->fresh()->notes)->toBe('VIP customer, handle with care.');
});

test('agent can clear customer notes', function () {
    $this->customer->update(['notes' => 'Old note']);

    $this->actingAs($this->user)
        ->patchJson("/customers/{$this->customer->id}", ['notes' => null])
        ->assertOk();

    expect($this->customer->fresh()->notes)->toBeNull();
});

test('notes cannot exceed 10000 characters', function () {
    $this->actingAs($this->user)
        ->patchJson("/customers/{$this->customer->id}", ['notes' => str_repeat('a', 10001)])
        ->assertUnprocessable();
});

test('agent cannot update notes for customer in another workspace', function () {
    $other    = \App\Models\Workspace::factory()->create();
    $customer = Customer::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->user)
        ->patchJson("/customers/{$customer->id}", ['notes' => 'Sneaky note'])
        ->assertForbidden();

    expect($customer->fresh()->notes)->toBeNull();
});
