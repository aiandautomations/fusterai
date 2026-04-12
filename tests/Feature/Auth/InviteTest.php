<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
});

test('invite page redirects to login when token or email is missing', function () {
    $this->get('/invite/accept/some-token')->assertRedirect('/login');
});

test('invite page renders when token and email are present', function () {
    $user  = agentUser($this->workspace);
    $token = Password::createToken($user);

    $this->get("/invite/accept/{$token}?email={$user->email}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Auth/AcceptInvite')
            ->where('token', $token)
            ->where('email', $user->email)
        );
});

test('agent can accept invite and set name and password', function () {
    $user  = agentUser($this->workspace);
    $token = Password::createToken($user);

    $this->post('/invite/accept', [
        'token'                 => $token,
        'email'                 => $user->email,
        'name'                  => 'Updated Name',
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertRedirect('/conversations');

    expect($user->fresh()->name)->toBe('Updated Name');
    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

test('accepting invite logs the user in', function () {
    $user  = agentUser($this->workspace);
    $token = Password::createToken($user);

    $this->post('/invite/accept', [
        'token'                 => $token,
        'email'                 => $user->email,
        'name'                  => 'Agent Name',
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);
});

test('invalid token returns error on invite acceptance', function () {
    $user = agentUser($this->workspace);

    $this->post('/invite/accept', [
        'token'                 => 'invalid-token',
        'email'                 => $user->email,
        'name'                  => 'Agent',
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});

test('invite acceptance requires all fields', function () {
    $this->post('/invite/accept', [])
        ->assertSessionHasErrors(['token', 'email', 'name', 'password']);
});
