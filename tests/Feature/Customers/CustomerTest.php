<?php

use App\Domains\Customer\Models\Customer;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user = agentUser($this->workspace);
});

test('customers index requires auth', function () {
    $this->get('/customers')->assertRedirect('/login');
});

test('agent can view customers list', function () {
    Customer::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get('/customers')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Customers/Index'));
});

test('customers are scoped to workspace', function () {
    $other = Workspace::factory()->create();
    Customer::factory()->count(2)->create(['workspace_id' => $other->id]);
    Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get('/customers')
        ->assertInertia(fn ($p) => $p->where('customers.total', 1));
});

test('agent can view a customer profile', function () {
    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get("/customers/{$customer->id}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Customers/Show'));
});

test('agent cannot view customer from another workspace', function () {
    $other = Workspace::factory()->create();
    $customer = Customer::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->user)
        ->get("/customers/{$customer->id}")
        ->assertForbidden();
});

test('customer search returns matching results as json', function () {
    Customer::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Alice Smith', 'email' => 'alice@example.com']);
    Customer::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Bob Jones',  'email' => 'bob@example.com']);

    $this->actingAs($this->user)
        ->getJson('/customers/search?q=alice')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['name' => 'Alice Smith']);
});

test('customer search is scoped to workspace', function () {
    $other = Workspace::factory()->create();
    Customer::factory()->create(['workspace_id' => $other->id, 'name' => 'Charlie External']);

    $this->actingAs($this->user)
        ->getJson('/customers/search?q=charlie')
        ->assertOk()
        ->assertJsonCount(0);
});
