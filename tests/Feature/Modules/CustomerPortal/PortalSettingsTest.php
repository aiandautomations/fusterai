<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->admin = adminUser($this->workspace);

    DB::table('modules')->updateOrInsert(
        ['alias' => 'CustomerPortal'],
        ['name' => 'Customer Portal', 'active' => true, 'version' => '1.0.0', 'config' => '[]', 'created_at' => now(), 'updated_at' => now()],
    );
});

test('admin can view portal settings page', function () {
    $this->actingAs($this->admin)
        ->get(route('settings.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Portal')
            ->has('portal.enabled')
            ->has('portal.name')
            ->has('portal.welcome_text')
            ->has('portal.url')
        );
});

test('agent cannot access portal settings', function () {
    $agent = agentUser($this->workspace);

    $this->actingAs($agent)
        ->get(route('settings.portal'))
        ->assertForbidden();
});

test('admin can enable the portal', function () {
    $this->actingAs($this->admin)
        ->post(route('settings.portal.update'), [
            'enabled' => true,
            'name' => 'Acme Support',
            'welcome_text' => 'Welcome to Acme support.',
        ])
        ->assertRedirect();

    $this->workspace->refresh();
    expect($this->workspace->settings['portal']['enabled'])->toBeTrue()
        ->and($this->workspace->settings['portal']['name'])->toBe('Acme Support')
        ->and($this->workspace->settings['portal']['welcome_text'])->toBe('Welcome to Acme support.');
});

test('admin can disable the portal', function () {
    $this->workspace->settings = ['portal' => ['enabled' => true]];
    $this->workspace->save();

    $this->actingAs($this->admin)
        ->post(route('settings.portal.update'), [
            'enabled' => false,
            'name' => 'Acme Support',
            'welcome_text' => '',
        ])
        ->assertRedirect();

    $this->workspace->refresh();
    expect($this->workspace->settings['portal']['enabled'])->toBeFalse();
});

test('portal settings page is not accessible when module is inactive', function () {
    DB::table('modules')->where('alias', 'CustomerPortal')->update(['active' => false]);

    $this->actingAs($this->admin)
        ->get(route('settings.portal'))
        ->assertNotFound();
});

test('portal name must not exceed 100 characters', function () {
    $this->actingAs($this->admin)
        ->post(route('settings.portal.update'), [
            'enabled' => true,
            'name' => str_repeat('a', 101),
            'welcome_text' => '',
        ])
        ->assertSessionHasErrors('name');
});

test('portal welcome text must not exceed 500 characters', function () {
    $this->actingAs($this->admin)
        ->post(route('settings.portal.update'), [
            'enabled' => true,
            'name' => 'Support',
            'welcome_text' => str_repeat('x', 501),
        ])
        ->assertSessionHasErrors('welcome_text');
});
