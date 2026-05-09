<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Hooks;
use Illuminate\Support\Facades\DB;
use Modules\ConversationRouting\Models\RoutingConfig;
use Modules\ConversationRouting\Providers\ConversationRoutingServiceProvider;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->admin = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'admin']);

    DB::table('modules')->updateOrInsert(
        ['alias' => 'ConversationRouting'],
        ['name' => 'Conversation Routing', 'active' => true, 'version' => '1.0.0', 'config' => '[]', 'created_at' => now(), 'updated_at' => now()],
    );

    app()->register(ConversationRoutingServiceProvider::class);
});

afterEach(function () {
    Hooks::reset();
});

// ── Settings page ─────────────────────────────────────────────────────────────

test('admin can view routing settings page', function () {
    $this->actingAs($this->admin)
        ->get(route('settings.routing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Routing'));
});

test('agent cannot access routing settings', function () {
    $agent = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);

    $this->actingAs($agent)
        ->get(route('settings.routing'))
        ->assertForbidden();
});

test('admin can save routing config', function () {
    $this->actingAs($this->admin)
        ->post(route('settings.routing.update'), [
            'configs' => [[
                'mailbox_id' => $this->mailbox->id,
                'mode' => 'round_robin',
                'active' => true,
            ]],
        ])
        ->assertRedirect();

    expect(RoutingConfig::where('mailbox_id', $this->mailbox->id)->where('active', true)->exists())->toBeTrue();
});

test('routing config from another workspace is rejected', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherMailbox = Mailbox::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $this->actingAs($this->admin)
        ->post(route('settings.routing.update'), [
            'configs' => [[
                'mailbox_id' => $otherMailbox->id,
                'mode' => 'round_robin',
                'active' => true,
            ]],
        ])
        ->assertStatus(404);
});

// ── Round-robin routing ───────────────────────────────────────────────────────

test('round-robin assigns conversation to first agent when no prior assignment', function () {
    $agent1 = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);
    $agent2 = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);
    $this->mailbox->users()->attach([$agent1->id, $agent2->id]);

    RoutingConfig::create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'mode' => 'round_robin',
        'active' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'assigned_user_id' => null,
    ]);

    Hooks::doAction('conversation.created', $conversation);

    expect($conversation->fresh()->assigned_user_id)->not->toBeNull();
});

test('round-robin advances to next agent on subsequent conversations', function () {
    $agent1 = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);
    $agent2 = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);
    $this->mailbox->users()->attach([$agent1->id, $agent2->id]);

    $config = RoutingConfig::create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'mode' => 'round_robin',
        'active' => true,
        'last_assigned_user_id' => $agent1->id,
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'assigned_user_id' => null,
    ]);

    Hooks::doAction('conversation.created', $conversation);

    expect($conversation->fresh()->assigned_user_id)->toBe($agent2->id);
});

test('already assigned conversations are not re-routed', function () {
    $agent1 = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);
    $agent2 = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);

    RoutingConfig::create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'mode' => 'round_robin',
        'active' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'assigned_user_id' => $agent1->id,
    ]);

    Hooks::doAction('conversation.created', $conversation);

    expect($conversation->fresh()->assigned_user_id)->toBe($agent1->id);
});

// ── Least-loaded routing ──────────────────────────────────────────────────────

test('least-loaded assigns to agent with fewer open conversations', function () {
    // freeAgent created first (lower id) to act as natural tiebreak if needed
    $freeAgent = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);
    $busyAgent = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);
    $this->mailbox->users()->attach([$freeAgent->id, $busyAgent->id]);

    // Give busyAgent 3 open conversations
    Conversation::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'assigned_user_id' => $busyAgent->id,
        'status' => 'open',
    ]);

    RoutingConfig::create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'mode' => 'least_loaded',
        'active' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'assigned_user_id' => null,
    ]);

    Hooks::doAction('conversation.created', $conversation);

    expect($conversation->fresh()->assigned_user_id)->toBe($freeAgent->id);
});

test('no assignment when no active routing config exists', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
        'assigned_user_id' => null,
    ]);

    Hooks::doAction('conversation.created', $conversation);

    expect($conversation->fresh()->assigned_user_id)->toBeNull();
});
