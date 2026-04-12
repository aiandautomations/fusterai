<?php

use App\Domains\Conversation\Models\CustomView;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->agent     = agentUser($this->workspace);
    $this->manager   = managerUser($this->workspace);
});

// ── Create ────────────────────────────────────────────────────────────────────

test('agent can create a personal custom view', function () {
    $this->actingAs($this->agent)
        ->post('/views', [
            'name'    => 'My Urgent',
            'color'   => '#ff0000',
            'filters' => ['priority' => 'urgent'],
        ])
        ->assertRedirect();

    $view = CustomView::where('workspace_id', $this->workspace->id)->first();
    expect($view)->not->toBeNull();
    expect($view->user_id)->toBe($this->agent->id);
    expect($view->is_shared)->toBeFalse();
    expect($view->filters['priority'])->toBe('urgent');
});

test('agent cannot create a shared view', function () {
    $this->actingAs($this->agent)
        ->post('/views', [
            'name'      => 'Shared View',
            'color'     => '#0000ff',
            'is_shared' => true,
            'filters'   => ['status' => 'open'],
        ])
        ->assertForbidden();
});

test('manager can create a shared view', function () {
    $this->actingAs($this->manager)
        ->post('/views', [
            'name'      => 'Team Unassigned',
            'color'     => '#00ff00',
            'is_shared' => true,
            'filters'   => ['assigned' => 'none'],
        ])
        ->assertRedirect();

    $view = CustomView::where('workspace_id', $this->workspace->id)->first();
    expect($view->is_shared)->toBeTrue();
    expect($view->user_id)->toBeNull();
});

test('view order auto-increments', function () {
    $this->actingAs($this->agent)
        ->post('/views', ['name' => 'First', 'color' => '#aaaaaa', 'filters' => ['status' => 'open']])
        ->assertRedirect();

    $this->actingAs($this->agent)
        ->post('/views', ['name' => 'Second', 'color' => '#bbbbbb', 'filters' => ['status' => 'open']])
        ->assertRedirect();

    $views = CustomView::where('workspace_id', $this->workspace->id)->orderBy('order')->get();
    expect($views[0]->order)->toBe(1);
    expect($views[1]->order)->toBe(2);
});

test('create strips null and empty filter values', function () {
    $this->actingAs($this->agent)
        ->post('/views', [
            'name'    => 'Clean View',
            'color'   => '#123456',
            'filters' => ['status' => 'open', 'priority' => null, 'assigned' => ''],
        ])
        ->assertRedirect();

    $view = CustomView::where('workspace_id', $this->workspace->id)->first();
    expect($view->filters)->toHaveKey('status');
    expect($view->filters)->not->toHaveKey('priority');
    expect($view->filters)->not->toHaveKey('assigned');
});

test('create validates color format', function () {
    $this->actingAs($this->agent)
        ->post('/views', [
            'name'    => 'Bad Color',
            'color'   => 'red',
            'filters' => ['status' => 'open'],
        ])
        ->assertSessionHasErrors('color');
});

test('create rejects mailbox from another workspace', function () {
    $other   = Workspace::factory()->create();
    $mailbox = Mailbox::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->agent)
        ->post('/views', [
            'name'    => 'Bad Mailbox',
            'color'   => '#123456',
            'filters' => ['status' => 'open', 'mailbox_id' => $mailbox->id],
        ])
        ->assertSessionHasErrors('filters.mailbox_id');
});

// ── Update ────────────────────────────────────────────────────────────────────

test('agent can update their own personal view', function () {
    $view = CustomView::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id'      => $this->agent->id,
        'is_shared'    => false,
    ]);

    $this->actingAs($this->agent)
        ->patch("/views/{$view->id}", ['name' => 'Updated Name'])
        ->assertRedirect();

    expect($view->fresh()->name)->toBe('Updated Name');
});

test('agent cannot update another agent\'s personal view', function () {
    $other = agentUser($this->workspace);
    $view  = CustomView::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id'      => $other->id,
        'is_shared'    => false,
    ]);

    $this->actingAs($this->agent)
        ->patch("/views/{$view->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

test('manager can update a shared view', function () {
    $view = CustomView::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id'      => null,
        'is_shared'    => true,
    ]);

    $this->actingAs($this->manager)
        ->patch("/views/{$view->id}", ['name' => 'Updated Shared'])
        ->assertRedirect();

    expect($view->fresh()->name)->toBe('Updated Shared');
});

test('agent cannot update a shared view', function () {
    $view = CustomView::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id'      => null,
        'is_shared'    => true,
    ]);

    $this->actingAs($this->agent)
        ->patch("/views/{$view->id}", ['name' => 'Attempt'])
        ->assertForbidden();
});

test('update from another workspace is forbidden', function () {
    $other = Workspace::factory()->create();
    $view  = CustomView::factory()->create([
        'workspace_id' => $other->id,
        'user_id'      => null,
        'is_shared'    => false,
    ]);

    $this->actingAs($this->agent)
        ->patch("/views/{$view->id}", ['name' => 'Cross-tenant'])
        ->assertForbidden();
});

test('update strips null and empty filter values', function () {
    $view = CustomView::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id'      => $this->agent->id,
        'is_shared'    => false,
        'filters'      => ['status' => 'open'],
    ]);

    $this->actingAs($this->agent)
        ->patch("/views/{$view->id}", [
            'filters' => ['status' => 'closed', 'priority' => null, 'assigned' => ''],
        ])
        ->assertRedirect();

    expect($view->fresh()->filters)->toHaveKey('status', 'closed');
    expect($view->fresh()->filters)->not->toHaveKey('priority');
    expect($view->fresh()->filters)->not->toHaveKey('assigned');
});

// ── Delete ────────────────────────────────────────────────────────────────────

test('agent can delete their own view', function () {
    $view = CustomView::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id'      => $this->agent->id,
        'is_shared'    => false,
    ]);

    $this->actingAs($this->agent)
        ->delete("/views/{$view->id}")
        ->assertRedirect();

    expect(CustomView::find($view->id))->toBeNull();
});

test('agent cannot delete another agent\'s view', function () {
    $other = agentUser($this->workspace);
    $view  = CustomView::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id'      => $other->id,
        'is_shared'    => false,
    ]);

    $this->actingAs($this->agent)
        ->delete("/views/{$view->id}")
        ->assertForbidden();

    expect(CustomView::find($view->id))->not->toBeNull();
});

test('manager can delete a shared view', function () {
    $view = CustomView::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id'      => null,
        'is_shared'    => true,
    ]);

    $this->actingAs($this->manager)
        ->delete("/views/{$view->id}")
        ->assertRedirect();

    expect(CustomView::find($view->id))->toBeNull();
});

test('agent cannot delete a shared view', function () {
    $view = CustomView::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id'      => null,
        'is_shared'    => true,
    ]);

    $this->actingAs($this->agent)
        ->delete("/views/{$view->id}")
        ->assertForbidden();
});

test('delete from another workspace is forbidden', function () {
    $other = Workspace::factory()->create();
    $view  = CustomView::factory()->create([
        'workspace_id' => $other->id,
        'user_id'      => null,
        'is_shared'    => false,
    ]);

    $this->actingAs($this->agent)
        ->delete("/views/{$view->id}")
        ->assertForbidden();
});
