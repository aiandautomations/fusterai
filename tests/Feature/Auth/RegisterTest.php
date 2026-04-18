<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;

test('registration page is accessible when no users exist', function () {
    $this->get('/register')->assertOk();
});

test('registration page redirects to login when users already exist', function () {
    User::factory()->create(['workspace_id' => workspace()->id]);

    $this->get('/register')->assertRedirect('/login');
});

test('registration creates workspace and super admin user', function () {
    $this->post('/register', [
        'workspace_name' => 'Acme Corp',
        'name' => 'Alice Admin',
        'email' => 'alice@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect('/conversations');

    $workspace = Workspace::where('name', 'Acme Corp')->first();
    expect($workspace)->not->toBeNull();

    $user = User::where('email', 'alice@acme.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('super_admin');
    expect($user->workspace_id)->toBe($workspace->id);
});

test('registration logs in the new user', function () {
    $this->post('/register', [
        'workspace_name' => 'Test Workspace',
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    expect(Auth::check())->toBeTrue();
    expect(Auth::user()->email)->toBe('admin@test.com');
});

test('registration is blocked when users already exist', function () {
    User::factory()->create(['workspace_id' => workspace()->id]);

    $this->post('/register', [
        'workspace_name' => 'Another Corp',
        'name' => 'Bob',
        'email' => 'bob@corp.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertForbidden();
});

test('registration requires all fields', function () {
    $this->post('/register', [])->assertSessionHasErrors([
        'workspace_name',
        'name',
        'email',
        'password',
    ]);
});

test('registration rejects mismatched passwords', function () {
    $this->post('/register', [
        'workspace_name' => 'Acme',
        'name' => 'Alice',
        'email' => 'alice@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'different456',
    ])->assertSessionHasErrors('password');
});

test('workspace slug is generated from workspace name', function () {
    $this->post('/register', [
        'workspace_name' => 'Hello World Corp',
        'name' => 'Admin',
        'email' => 'admin@hello.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    expect(Workspace::where('slug', 'hello-world-corp')->exists())->toBeTrue();
});
