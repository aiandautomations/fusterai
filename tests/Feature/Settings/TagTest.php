<?php

use App\Domains\Conversation\Models\Tag;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->manager   = managerUser($this->workspace);
});

test('tags index requires manager role', function () {
    $agent = agentUser($this->workspace);

    $this->actingAs($agent)->get('/tags')->assertForbidden();
});

test('manager can view tags list', function () {
    Tag::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->manager)
        ->get('/tags')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Tags')->has('tags', 2));
});

test('manager can create a tag', function () {
    $this->actingAs($this->manager)
        ->post('/tags', ['name' => 'billing', 'color' => '#FF0000'])
        ->assertRedirect();

    expect(Tag::where('name', 'billing')->where('workspace_id', $this->workspace->id)->exists())->toBeTrue();
});

test('tag name must be unique within workspace', function () {
    Tag::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'billing', 'color' => '#FF0000']);

    $this->actingAs($this->manager)
        ->post('/tags', ['name' => 'billing', 'color' => '#00FF00'])
        ->assertSessionHasErrors('name');
});

test('manager can update a tag', function () {
    $tag = Tag::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'old', 'color' => '#AAAAAA']);

    $this->actingAs($this->manager)
        ->patch("/tags/{$tag->id}", ['name' => 'new', 'color' => '#BBBBBB'])
        ->assertRedirect();

    expect($tag->fresh()->name)->toBe('new');
});

test('manager can delete a tag', function () {
    $tag = Tag::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->manager)
        ->delete("/tags/{$tag->id}")
        ->assertRedirect();

    expect(Tag::find($tag->id))->toBeNull();
});

test('manager cannot modify tag from another workspace', function () {
    $other = Workspace::factory()->create();
    $tag   = Tag::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->manager)
        ->delete("/tags/{$tag->id}")
        ->assertForbidden();
});
