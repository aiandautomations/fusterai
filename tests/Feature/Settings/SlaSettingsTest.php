<?php

use App\Domains\AI\Models\Module;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Modules\SlaManager\Models\SlaPolicy;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->admin = User::factory()->create([
        'workspace_id' => $this->workspace->id,
        'role' => 'admin',
    ]);

    // Create active Module record so the module.active:SlaManager middleware passes.
    // Routes are already registered at boot by ModuleServiceProvider scanning all
    // Modules/*/Routes/web.php files — no need to re-register the ServiceProvider.
    Module::create([
        'alias' => 'SlaManager',
        'name' => 'SLA Manager',
        'active' => true,
        'version' => '1.0.0',
        'config' => [],
    ]);

    // Flush the module-active cache so the middleware reads from the test DB.
    Cache::forget('module.active.SlaManager');
});

test('sla settings page is accessible to authenticated users', function () {
    $this->actingAs($this->admin)
        ->get('/settings/sla')
        ->assertOk();
});

test('sla settings page returns policies with defaults', function () {
    $response = $this->actingAs($this->admin)
        ->get('/settings/sla')
        ->assertOk();

    $policies = $response->original->getData()['page']['props']['policies'];
    expect($policies)->toHaveCount(4);
    expect(array_column($policies, 'priority'))->toBe(['urgent', 'high', 'normal', 'low']);
});

test('sla policies can be saved', function () {
    $policies = [
        ['priority' => 'urgent', 'first_response_minutes' => 30,   'resolution_minutes' => 120,  'active' => true],
        ['priority' => 'high',   'first_response_minutes' => 120,  'resolution_minutes' => 480,  'active' => true],
        ['priority' => 'normal', 'first_response_minutes' => 240,  'resolution_minutes' => 1440, 'active' => true],
        ['priority' => 'low',    'first_response_minutes' => 480,  'resolution_minutes' => 2880, 'active' => false],
    ];

    $this->actingAs($this->admin)
        ->post('/settings/sla', ['policies' => $policies])
        ->assertRedirect();

    expect(SlaPolicy::where('workspace_id', $this->workspace->id)->count())->toBe(4);

    $urgent = SlaPolicy::where('workspace_id', $this->workspace->id)->where('priority', 'urgent')->first();
    expect($urgent->first_response_minutes)->toBe(30);
    expect($urgent->resolution_minutes)->toBe(120);

    $low = SlaPolicy::where('workspace_id', $this->workspace->id)->where('priority', 'low')->first();
    expect($low->active)->toBeFalse();
});

test('saving sla policies updates existing records', function () {
    SlaPolicy::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Urgent Priority SLA',
        'priority' => 'urgent',
        'first_response_minutes' => 60,
        'resolution_minutes' => 240,
        'active' => true,
    ]);

    $policies = [
        ['priority' => 'urgent', 'first_response_minutes' => 15,  'resolution_minutes' => 60,   'active' => true],
        ['priority' => 'high',   'first_response_minutes' => 120, 'resolution_minutes' => 480,  'active' => true],
        ['priority' => 'normal', 'first_response_minutes' => 240, 'resolution_minutes' => 1440, 'active' => true],
        ['priority' => 'low',    'first_response_minutes' => 480, 'resolution_minutes' => 2880, 'active' => true],
    ];

    $this->actingAs($this->admin)->post('/settings/sla', ['policies' => $policies])->assertRedirect();

    expect(SlaPolicy::where('workspace_id', $this->workspace->id)->where('priority', 'urgent')->count())->toBe(1);

    $urgent = SlaPolicy::where('workspace_id', $this->workspace->id)->where('priority', 'urgent')->first();
    expect($urgent->first_response_minutes)->toBe(15);
});

test('sla update validates that exactly 4 policies are required', function () {
    $this->actingAs($this->admin)
        ->post('/settings/sla', ['policies' => []])
        ->assertSessionHasErrors('policies');
});

test('sla update rejects invalid priority values', function () {
    $policies = [
        ['priority' => 'invalid', 'first_response_minutes' => 30, 'resolution_minutes' => 120, 'active' => true],
        ['priority' => 'high',    'first_response_minutes' => 30, 'resolution_minutes' => 120, 'active' => true],
        ['priority' => 'normal',  'first_response_minutes' => 30, 'resolution_minutes' => 120, 'active' => true],
        ['priority' => 'low',     'first_response_minutes' => 30, 'resolution_minutes' => 120, 'active' => true],
    ];

    $this->actingAs($this->admin)
        ->post('/settings/sla', ['policies' => $policies])
        ->assertSessionHasErrors('policies.0.priority');
});
