<?php

use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->admin = adminUser($this->workspace);
    $this->agent = agentUser($this->workspace);
});

// ── index ──────────────────────────────────────────────────────────────────────

test('any agent can view the mailboxes list', function () {
    Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->agent)
        ->get('/mailboxes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Mailboxes/Index'));
});

test('mailboxes index requires auth', function () {
    $this->get('/mailboxes')->assertRedirect('/login');
});

// ── create ─────────────────────────────────────────────────────────────────────

test('admin can view the create mailbox page', function () {
    $this->actingAs($this->admin)
        ->get('/mailboxes/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Mailboxes/Create'));
});

test('agent cannot view the create mailbox page', function () {
    $this->actingAs($this->agent)
        ->get('/mailboxes/create')
        ->assertForbidden();
});

// ── store ──────────────────────────────────────────────────────────────────────

test('admin can create a mailbox', function () {
    $this->actingAs($this->admin)
        ->post('/mailboxes', [
            'name' => 'Support',
            'email' => 'support@example.com',
        ])
        ->assertRedirect(route('mailboxes.index'));

    expect(Mailbox::where('name', 'Support')->where('workspace_id', $this->workspace->id)->exists())->toBeTrue();
});

test('agent cannot create a mailbox', function () {
    $this->actingAs($this->agent)
        ->post('/mailboxes', [
            'name' => 'Support',
            'email' => 'support@example.com',
        ])
        ->assertForbidden();
});

test('mailbox creation requires name and email', function () {
    $this->actingAs($this->admin)
        ->post('/mailboxes', [])
        ->assertSessionHasErrors(['name', 'email']);
});

// ── edit ───────────────────────────────────────────────────────────────────────

test('admin can view mailbox settings page', function () {
    $mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->admin)
        ->get("/mailboxes/{$mailbox->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Mailboxes/Settings'));
});

test('agent cannot view mailbox settings page', function () {
    $mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->agent)
        ->get("/mailboxes/{$mailbox->id}/edit")
        ->assertForbidden();
});

// ── update ─────────────────────────────────────────────────────────────────────

test('admin can update mailbox name and signature', function () {
    $mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->admin)
        ->patch("/mailboxes/{$mailbox->id}", [
            'name' => 'Updated Name',
            'signature' => 'Best regards',
        ])
        ->assertRedirect();

    expect($mailbox->fresh()->name)->toBe('Updated Name');
    expect($mailbox->fresh()->signature)->toBe('Best regards');
});

test('agent cannot update a mailbox', function () {
    $mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->agent)
        ->patch("/mailboxes/{$mailbox->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

// ── destroy ────────────────────────────────────────────────────────────────────

test('admin can delete a mailbox', function () {
    $mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->admin)
        ->delete("/mailboxes/{$mailbox->id}")
        ->assertRedirect(route('mailboxes.index'));

    expect(Mailbox::find($mailbox->id))->toBeNull();
});

test('agent cannot delete a mailbox', function () {
    $mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->agent)
        ->delete("/mailboxes/{$mailbox->id}")
        ->assertForbidden();
});

// ── cross-workspace security ───────────────────────────────────────────────────

test('cannot edit mailbox from another workspace', function () {
    $other = Workspace::factory()->create();
    $mailbox = Mailbox::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->admin)
        ->patch("/mailboxes/{$mailbox->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

test('cannot delete mailbox from another workspace', function () {
    $other = Workspace::factory()->create();
    $mailbox = Mailbox::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->admin)
        ->delete("/mailboxes/{$mailbox->id}")
        ->assertForbidden();
});
