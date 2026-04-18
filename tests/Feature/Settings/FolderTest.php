<?php

use App\Domains\Conversation\Models\Folder;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->manager = managerUser($this->workspace);
    $this->agent = agentUser($this->workspace);
});

// ── index ──────────────────────────────────────────────────────────────────────

test('any agent can view the folders settings page', function () {
    $this->actingAs($this->agent)
        ->get('/settings/folders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Folders'));
});

test('folders page requires auth', function () {
    $this->get('/settings/folders')->assertRedirect('/login');
});

// ── store ──────────────────────────────────────────────────────────────────────

test('manager can create a folder', function () {
    $this->actingAs($this->manager)
        ->post('/settings/folders', [
            'name' => 'VIP Customers',
            'color' => '#ff0000',
            'icon' => 'star',
        ])
        ->assertRedirect();

    expect(Folder::where('name', 'VIP Customers')->where('workspace_id', $this->workspace->id)->exists())->toBeTrue();
});

test('agent cannot create a folder', function () {
    $this->actingAs($this->agent)
        ->post('/settings/folders', [
            'name' => 'VIP',
            'color' => '#ff0000',
        ])
        ->assertForbidden();
});

test('folder color must be a valid hex', function () {
    $this->actingAs($this->manager)
        ->post('/settings/folders', [
            'name' => 'Test',
            'color' => 'red',
        ])
        ->assertSessionHasErrors('color');
});

test('folder creation assigns sequential order', function () {
    Folder::create([
        'workspace_id' => $this->workspace->id,
        'created_by_user_id' => $this->manager->id,
        'name' => 'First',
        'color' => '#aaaaaa',
        'icon' => 'folder',
        'order' => 1,
    ]);

    $this->actingAs($this->manager)
        ->post('/settings/folders', [
            'name' => 'Second',
            'color' => '#bbbbbb',
        ])
        ->assertRedirect();

    $second = Folder::where('name', 'Second')->first();
    expect($second->order)->toBe(2);
});

// ── update ─────────────────────────────────────────────────────────────────────

test('manager can update a folder', function () {
    $folder = Folder::create([
        'workspace_id' => $this->workspace->id,
        'created_by_user_id' => $this->manager->id,
        'name' => 'Old Name',
        'color' => '#000000',
        'icon' => 'folder',
        'order' => 1,
    ]);

    $this->actingAs($this->manager)
        ->patch("/settings/folders/{$folder->id}", [
            'name' => 'New Name',
            'color' => '#ffffff',
        ])
        ->assertRedirect();

    expect($folder->fresh()->name)->toBe('New Name');
});

test('agent cannot update a folder', function () {
    $folder = Folder::create([
        'workspace_id' => $this->workspace->id,
        'created_by_user_id' => $this->manager->id,
        'name' => 'Protected',
        'color' => '#000000',
        'icon' => 'folder',
        'order' => 1,
    ]);

    $this->actingAs($this->agent)
        ->patch("/settings/folders/{$folder->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

// ── destroy ────────────────────────────────────────────────────────────────────

test('manager can delete a folder', function () {
    $folder = Folder::create([
        'workspace_id' => $this->workspace->id,
        'created_by_user_id' => $this->manager->id,
        'name' => 'To Delete',
        'color' => '#000000',
        'icon' => 'folder',
        'order' => 1,
    ]);

    $this->actingAs($this->manager)
        ->delete("/settings/folders/{$folder->id}")
        ->assertRedirect();

    expect(Folder::find($folder->id))->toBeNull();
});

test('agent cannot delete a folder', function () {
    $folder = Folder::create([
        'workspace_id' => $this->workspace->id,
        'created_by_user_id' => $this->manager->id,
        'name' => 'Protected',
        'color' => '#000000',
        'icon' => 'folder',
        'order' => 1,
    ]);

    $this->actingAs($this->agent)
        ->delete("/settings/folders/{$folder->id}")
        ->assertForbidden();
});

// ── cross-workspace security ───────────────────────────────────────────────────

test('cannot update folder from another workspace', function () {
    $other = Workspace::factory()->create();
    $otherManager = managerUser($other);
    $folder = Folder::create([
        'workspace_id' => $other->id,
        'created_by_user_id' => $otherManager->id,
        'name' => 'Other Folder',
        'color' => '#000000',
        'icon' => 'folder',
        'order' => 1,
    ]);

    $this->actingAs($this->manager)
        ->patch("/settings/folders/{$folder->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

test('cannot delete folder from another workspace', function () {
    $other = Workspace::factory()->create();
    $otherManager = managerUser($other);
    $folder = Folder::create([
        'workspace_id' => $other->id,
        'created_by_user_id' => $otherManager->id,
        'name' => 'Other Folder',
        'color' => '#000000',
        'icon' => 'folder',
        'order' => 1,
    ]);

    $this->actingAs($this->manager)
        ->delete("/settings/folders/{$folder->id}")
        ->assertForbidden();
});
