<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create([
        'settings' => ['portal' => ['enabled' => true]],
    ]);

    DB::table('modules')->updateOrInsert(
        ['alias' => 'CustomerPortal'],
        ['name' => 'Customer Portal', 'active' => true, 'version' => '1.0.0', 'config' => '[]', 'created_at' => now(), 'updated_at' => now()],
    );

    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('unauthenticated customer is redirected to login', function () {
    $this->get(route('portal.tickets.index', $this->workspace->slug))
        ->assertRedirect(route('portal.login', $this->workspace->slug));
});

test('customer from different workspace cannot access portal', function () {
    $otherWorkspace = Workspace::factory()->create([
        'settings' => ['portal' => ['enabled' => true]],
    ]);
    DB::table('modules')->updateOrInsert(
        ['alias' => 'CustomerPortal'],
        ['name' => 'Customer Portal', 'active' => true, 'version' => '1.0.0', 'config' => '[]', 'created_at' => now(), 'updated_at' => now()],
    );
    $otherCustomer = Customer::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $this->actingAs($otherCustomer, 'customer_portal')
        ->get(route('portal.tickets.index', $this->workspace->slug))
        ->assertRedirect(route('portal.login', $this->workspace->slug));
});

test('customer can view their tickets list', function () {
    Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $this->customer->id,
        'subject' => 'Help with login',
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.tickets.index', $this->workspace->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Portal/Tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.subject', 'Help with login')
        );
});

test('customer only sees their own tickets', function () {
    $otherCustomer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $otherCustomer->id,
    ]);

    Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.tickets.index', $this->workspace->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
});

test('customer can view new ticket form', function () {
    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.tickets.create', $this->workspace->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Tickets/Create'));
});

test('customer can submit a new ticket', function () {
    $this->actingAs($this->customer, 'customer_portal')
        ->post(route('portal.tickets.store', $this->workspace->slug), [
            'subject' => 'My account is locked',
            'body' => 'I cannot log in to my account since yesterday.',
        ])
        ->assertRedirect();

    $conversation = Conversation::where('customer_id', $this->customer->id)->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->subject)->toBe('My account is locked')
        ->and($conversation->workspace_id)->toBe($this->workspace->id)
        ->and($conversation->channel_type)->toBe(ChannelType::Portal)
        ->and($conversation->status)->toBe(ConversationStatus::Open);

    expect($conversation->threads)->toHaveCount(1)
        ->and($conversation->threads->first()->body)->toBe('I cannot log in to my account since yesterday.');
});

test('ticket submission requires subject and body', function () {
    $this->actingAs($this->customer, 'customer_portal')
        ->post(route('portal.tickets.store', $this->workspace->slug), [])
        ->assertSessionHasErrors(['subject', 'body']);
});

test('customer can view their ticket', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $this->customer->id,
        'subject' => 'Need billing help',
    ]);

    $conversation->threads()->create([
        'customer_id' => $this->customer->id,
        'type' => 'message',
        'body' => 'Please help me with my invoice.',
        'source' => 'portal',
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.tickets.show', [$this->workspace->slug, $conversation->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Portal/Tickets/Show')
            ->where('ticket.subject', 'Need billing help')
            ->has('ticket.threads', 1)
        );
});

test('customer cannot view another customer ticket', function () {
    $otherCustomer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $otherCustomer->id,
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.tickets.show', [$this->workspace->slug, $conversation->id]))
        ->assertForbidden();
});

test('customer cannot view ticket from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create([
        'settings' => ['portal' => ['enabled' => true]],
    ]);
    $otherCustomer = Customer::factory()->create(['workspace_id' => $otherWorkspace->id]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'customer_id' => $otherCustomer->id,
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.tickets.show', [$this->workspace->slug, $conversation->id]))
        ->assertForbidden();
});

test('customer can reply to their ticket', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $this->customer->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->post(route('portal.tickets.reply', [$this->workspace->slug, $conversation->id]), [
            'body' => 'Here is the additional info you requested.',
        ])
        ->assertRedirect();

    expect($conversation->threads()->where('type', 'message')->count())->toBe(1);
    expect($conversation->fresh()->status)->toBe(ConversationStatus::Open);
});

test('customer cannot reply to another customers ticket', function () {
    $otherCustomer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $otherCustomer->id,
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->post(route('portal.tickets.reply', [$this->workspace->slug, $conversation->id]), [
            'body' => 'Trying to inject a reply.',
        ])
        ->assertForbidden();
});

test('reply body is required', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->post(route('portal.tickets.reply', [$this->workspace->slug, $conversation->id]), [
            'body' => '',
        ])
        ->assertSessionHasErrors('body');
});
