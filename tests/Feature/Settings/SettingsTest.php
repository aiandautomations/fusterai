<?php

use App\Ai\Agents\SummarizationAgent;
use App\Models\Workspace;
use Illuminate\Support\Facades\Crypt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

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

test('test-connection returns ok when API key is valid', function () {
    SummarizationAgent::fake([
        new AgentResponse(
            invocationId: 'test',
            text: 'ok',
            usage: new Usage,
            meta: new Meta('anthropic', 'claude-haiku-4-5-20251001'),
        ),
    ]);

    $this->workspace->update([
        'settings' => ['ai_api_key' => Crypt::encryptString('sk-test-key'), 'ai_provider' => 'anthropic'],
    ]);

    $this->actingAs($this->admin)
        ->postJson('/settings/ai/test-connection')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('test-connection returns error when no API key is saved', function () {
    $this->actingAs($this->admin)
        ->postJson('/settings/ai/test-connection')
        ->assertOk()
        ->assertJson(['ok' => false]);
});

test('test-connection is forbidden for non-admin users', function () {
    $manager = managerUser($this->workspace);

    $this->actingAs($manager)
        ->postJson('/settings/ai/test-connection')
        ->assertForbidden();
});

test('admin can view live chat settings', function () {
    $this->actingAs($this->admin)
        ->get('/settings/live-chat')
        ->assertOk();
});

test('admin can update live chat settings', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/live-chat', [
            'greeting'      => 'Hello!',
            'color'         => '#7c3aed',
            'position'      => 'bottom-right',
            'launcher_text' => 'Chat with us',
        ])
        ->assertRedirect();

    expect($this->workspace->fresh()->settings['live_chat']['greeting'])->toBe('Hello!');
});

test('live chat update rejects invalid position', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/live-chat', [
            'greeting'      => 'Hello',
            'color'         => '#000000',
            'position'      => 'top-center',
            'launcher_text' => 'Chat',
        ])
        ->assertSessionHasErrors('position');
});

test('admin can view appearance settings', function () {
    $this->actingAs($this->admin)
        ->get('/settings/appearance')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Settings/Appearance')
            ->has('appearance.mode')
            ->has('appearance.color')
        );
});

test('admin can update appearance settings', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/appearance', [
            'mode'     => 'dark',
            'color'    => 'blue',
            'font'     => 'inter',
            'radius'   => 'md',
            'contrast' => 'balanced',
        ])
        ->assertRedirect();

    expect($this->workspace->fresh()->settings['appearance']['mode'])->toBe('dark');
    expect($this->workspace->fresh()->settings['appearance']['color'])->toBe('blue');
});

test('appearance update rejects invalid mode', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/appearance', [
            'mode'     => 'rainbow',
            'color'    => 'blue',
            'font'     => 'inter',
            'radius'   => 'md',
            'contrast' => 'balanced',
        ])
        ->assertSessionHasErrors('mode');
});

test('admin can view modules settings page', function () {
    $this->actingAs($this->admin)
        ->get('/settings/modules')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Modules')->has('modules'));
});

test('admin can view email settings page', function () {
    $this->actingAs($this->admin)
        ->get('/settings/email')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Email'));
});

test('admin can view audit log page', function () {
    $this->actingAs($this->admin)
        ->get('/settings/audit-log')
        ->assertOk();
});

test('agent cannot access audit log', function () {
    $agent = agentUser($this->workspace);

    $this->actingAs($agent)
        ->get('/settings/audit-log')
        ->assertForbidden();
});
