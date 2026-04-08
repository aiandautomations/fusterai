<?php

use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->admin     = adminUser($this->workspace);
    $this->agent     = agentUser($this->workspace);
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
});

// ── index ──────────────────────────────────────────────────────────────────────

test('any agent can view the users settings page', function () {
    $this->actingAs($this->agent)
        ->get('/settings/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Users'));
});

test('users page requires auth', function () {
    $this->get('/settings/users')->assertRedirect('/login');
});

// ── store (invite) ─────────────────────────────────────────────────────────────

test('admin can invite a new user', function () {
    Password::shouldReceive('sendResetLink')->once()->andReturn(Password::RESET_LINK_SENT);

    $this->actingAs($this->admin)
        ->post('/settings/users', [
            'name'  => 'New Agent',
            'email' => 'newagent@example.com',
            'role'  => 'agent',
        ])
        ->assertRedirect();

    expect(User::where('email', 'newagent@example.com')->exists())->toBeTrue();
});

test('agent cannot invite users', function () {
    $this->actingAs($this->agent)
        ->post('/settings/users', [
            'name'  => 'New Agent',
            'email' => 'newagent2@example.com',
            'role'  => 'agent',
        ])
        ->assertForbidden();
});

test('invite requires valid role', function () {
    $this->actingAs($this->admin)
        ->post('/settings/users', [
            'name'  => 'Test',
            'email' => 'test@example.com',
            'role'  => 'superuser',
        ])
        ->assertSessionHasErrors('role');
});

// ── update ─────────────────────────────────────────────────────────────────────

test('admin can update a user name and role', function () {
    $target = agentUser($this->workspace);

    $this->actingAs($this->admin)
        ->patch("/settings/users/{$target->id}", [
            'name' => 'Updated Name',
            'role' => 'agent',
        ])
        ->assertRedirect();

    expect($target->fresh()->name)->toBe('Updated Name');
});

test('agent cannot update another user', function () {
    $target = agentUser($this->workspace);

    $this->actingAs($this->agent)
        ->patch("/settings/users/{$target->id}", [
            'name' => 'Hijacked',
            'role' => 'agent',
        ])
        ->assertForbidden();
});

// ── destroy ────────────────────────────────────────────────────────────────────

test('admin can remove a user from the workspace', function () {
    $target = agentUser($this->workspace);

    $this->actingAs($this->admin)
        ->delete("/settings/users/{$target->id}")
        ->assertRedirect();

    expect(User::find($target->id))->toBeNull();
});

test('admin cannot delete their own account', function () {
    $this->actingAs($this->admin)
        ->delete("/settings/users/{$this->admin->id}")
        ->assertForbidden();
});

test('agent cannot delete users', function () {
    $target = agentUser($this->workspace);

    $this->actingAs($this->agent)
        ->delete("/settings/users/{$target->id}")
        ->assertForbidden();
});

// ── updateMailboxes ────────────────────────────────────────────────────────────

test('admin can assign mailbox access to a user', function () {
    $target = agentUser($this->workspace);

    $this->actingAs($this->admin)
        ->patch("/settings/users/{$target->id}/mailboxes", [
            'mailbox_ids' => [$this->mailbox->id],
        ])
        ->assertRedirect();

    expect($target->mailboxes()->where('mailboxes.id', $this->mailbox->id)->exists())->toBeTrue();
});

test('cannot assign a mailbox from another workspace', function () {
    $other        = Workspace::factory()->create();
    $otherMailbox = Mailbox::factory()->create(['workspace_id' => $other->id]);
    $target       = agentUser($this->workspace);

    $this->actingAs($this->admin)
        ->patch("/settings/users/{$target->id}/mailboxes", [
            'mailbox_ids' => [$otherMailbox->id],
        ])
        ->assertSessionHasErrors('mailbox_ids.0');
});

// ── cross-workspace security ───────────────────────────────────────────────────

test('cannot update a user from another workspace', function () {
    $other  = Workspace::factory()->create();
    $target = agentUser($other);

    $this->actingAs($this->admin)
        ->patch("/settings/users/{$target->id}", [
            'name' => 'Hijacked',
            'role' => 'agent',
        ])
        ->assertForbidden();
});
