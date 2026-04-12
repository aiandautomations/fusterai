<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    // Passport requires a personal access client to exist in this DB transaction
    Artisan::call('passport:client', ['--personal' => true, '--name' => 'Test PAC', '--no-interaction' => true]);

    $this->workspace = Workspace::factory()->create();
    $this->admin     = adminUser($this->workspace);
});

test('api keys page is accessible to admin', function () {
    $this->actingAs($this->admin)
        ->get('/settings/api-keys')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/ApiKeys')->has('tokens'));
});

test('admin can create an api key', function () {
    $this->actingAs($this->admin)
        ->post('/settings/api-keys', ['name' => 'My Integration'])
        ->assertRedirect('/settings/api-keys')
        ->assertSessionHas('token')
        ->assertSessionHas('success');

    expect($this->admin->tokens()->where('name', 'My Integration')->exists())->toBeTrue();
});

test('api key name is required', function () {
    $this->actingAs($this->admin)
        ->post('/settings/api-keys', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('admin can revoke an api key', function () {
    $token = $this->admin->createToken('Test Token');
    $id    = $token->token->id;

    $this->actingAs($this->admin)
        ->delete("/settings/api-keys/{$id}")
        ->assertRedirect('/settings/api-keys');

    expect($this->admin->tokens()->findOrFail($id)->revoked)->toBeTrue();
});

test('admin cannot revoke a token belonging to another user', function () {
    $other      = adminUser($this->workspace);
    $otherToken = $other->createToken('Other Token');
    $id         = $otherToken->token->id;

    $this->actingAs($this->admin)
        ->delete("/settings/api-keys/{$id}")
        ->assertNotFound();
});

test('api keys page lists only non-revoked tokens', function () {
    $this->admin->createToken('Active Token');
    $revokedToken = $this->admin->createToken('Revoked Token');
    $revokedToken->token->update(['revoked' => true]);

    $this->actingAs($this->admin)
        ->get('/settings/api-keys')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('tokens.0.name', 'Active Token')->count('tokens', 1));
});
