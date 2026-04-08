<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user      = agentUser($this->workspace);
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer  = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('search requires auth', function () {
    $this->getJson('/search?q=hello')->assertUnauthorized();
});

test('short query returns empty results without searching', function () {
    $this->actingAs($this->user)
        ->getJson('/search?q=a')
        ->assertOk()
        ->assertJson(['results' => []]);
});

test('empty query returns empty results', function () {
    $this->actingAs($this->user)
        ->getJson('/search')
        ->assertOk()
        ->assertJson(['results' => []]);
});

test('search returns matching conversations', function () {
    Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'subject'      => 'My order is missing',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=order')
        ->assertOk();

    expect($response->json('results'))->not->toBeEmpty();
    expect($response->json('results.0.subject'))->toContain('order');
});

test('search results are scoped to the authenticated workspace', function () {
    $other         = Workspace::factory()->create();
    $otherMailbox  = Mailbox::factory()->create(['workspace_id' => $other->id]);
    $otherCustomer = Customer::factory()->create(['workspace_id' => $other->id]);

    Conversation::factory()->create([
        'workspace_id' => $other->id,
        'mailbox_id'   => $otherMailbox->id,
        'customer_id'  => $otherCustomer->id,
        'subject'      => 'secret billing issue',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=billing')
        ->assertOk();

    expect($response->json('results'))->toBeEmpty();
});

test('search result includes expected fields', function () {
    Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id'   => $this->mailbox->id,
        'customer_id'  => $this->customer->id,
        'subject'      => 'Refund request pending',
    ]);

    $result = $this->actingAs($this->user)
        ->getJson('/search?q=refund')
        ->assertOk()
        ->json('results.0');

    expect($result)->toHaveKeys(['id', 'subject', 'status', 'customer', 'mailbox', 'url']);
});
