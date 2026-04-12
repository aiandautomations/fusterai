<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
    $this->workspace = Workspace::factory()->create();
    $this->user      = agentUser($this->workspace);
});

test('agent can update their status', function () {
    $this->actingAs($this->user)
        ->patch('/profile/status', ['status' => 'away'])
        ->assertRedirect();

    expect($this->user->fresh()->status)->toBe('away');
});

test('all valid statuses are accepted', function () {
    foreach (['online', 'away', 'busy', 'offline'] as $status) {
        $this->actingAs($this->user)
            ->patch('/profile/status', ['status' => $status])
            ->assertRedirect();

        expect($this->user->fresh()->status)->toBe($status);
    }
});

test('invalid status is rejected', function () {
    $this->actingAs($this->user)
        ->patch('/profile/status', ['status' => 'invisible'])
        ->assertSessionHasErrors('status');
});

test('status update requires authentication', function () {
    $this->patch('/profile/status', ['status' => 'online'])
        ->assertRedirect('/login');
});
