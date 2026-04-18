<?php

use App\Models\User;
use App\Models\Workspace;

test('login page renders', function () {
    $this->get('/login')->assertOk();
});

test('user can login with valid credentials', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create([
        'workspace_id' => $workspace->id,
        'password' => bcrypt('password'),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('login fails with wrong password', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create(['workspace_id' => $workspace->id]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated user is redirected away from login', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->get('/login')
        ->assertRedirect();
});

test('user can logout', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
