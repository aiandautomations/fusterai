<?php

use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->admin     = adminUser($this->workspace);
});

test('settings index redirects to general', function () {
    $this->actingAs($this->admin)
        ->get('/settings')
        ->assertRedirect('/settings/general');
});

test('general settings requires admin role', function () {
    $manager = managerUser($this->workspace);

    $this->actingAs($manager)->get('/settings/general')->assertForbidden();
});

test('admin can view general settings page', function () {
    $this->actingAs($this->admin)
        ->get('/settings/general')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/General'));
});

test('admin can update workspace name', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/general', ['name' => 'New Workspace Name'])
        ->assertRedirect();

    expect($this->workspace->fresh()->name)->toBe('New Workspace Name');
});

test('workspace name is required when updating general settings', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/general', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('admin can view AI settings page', function () {
    $this->actingAs($this->admin)
        ->get('/settings/ai')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/AIConfig'));
});

test('admin can update AI settings', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/ai', [
            'provider'                    => 'anthropic',
            'api_key'                     => 'sk-test-key',
            'model'                       => 'claude-opus-4-6',
            'feature_reply_suggestions'   => true,
            'feature_auto_categorization' => true,
            'feature_summarization'       => true,
            'rag_top_k'                   => 5,
            'rag_min_score'               => 0.7,
        ])
        ->assertRedirect();
});

test('admin can view live chat settings', function () {
    $this->actingAs($this->admin)
        ->get('/settings/live-chat')
        ->assertOk();
});
