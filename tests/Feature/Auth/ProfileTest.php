<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->workspace = Workspace::factory()->create();
    $this->user      = User::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('profile page is accessible', function () {
    $this->actingAs($this->user)
        ->get('/profile')
        ->assertOk();
});

test('profile can be updated', function () {
    $this->actingAs($this->user)
        ->patch('/profile', ['name' => 'New Name', 'email' => 'new@example.com'])
        ->assertRedirect();

    expect($this->user->fresh()->name)->toBe('New Name');
    expect($this->user->fresh()->email)->toBe('new@example.com');
});

test('avatar can be uploaded', function () {
    $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

    $this->actingAs($this->user)
        ->post('/profile/avatar', ['avatar' => $file])
        ->assertRedirect();

    $fresh = $this->user->fresh();
    expect($fresh->avatar)->not->toBeNull();
    expect($fresh->avatar)->toContain('users/avatars/');
    Storage::disk('public')->assertExists(str_replace(Storage::disk('public')->url(''), '', $fresh->avatar));
});

test('old avatar is deleted when new one is uploaded', function () {
    // Upload first avatar
    $first = UploadedFile::fake()->image('first.jpg');
    $this->actingAs($this->user)->post('/profile/avatar', ['avatar' => $first]);
    $firstUrl = $this->user->fresh()->avatar;

    // Upload second avatar
    $second = UploadedFile::fake()->image('second.jpg');
    $this->actingAs($this->user)->post('/profile/avatar', ['avatar' => $second]);

    $secondUrl = $this->user->fresh()->avatar;
    expect($secondUrl)->not->toBe($firstUrl);

    // Old file should be gone
    $oldPath = str_replace(url('/storage') . '/', '', $firstUrl);
    Storage::disk('public')->assertMissing($oldPath);
});

test('avatar upload requires an image', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $this->actingAs($this->user)
        ->post('/profile/avatar', ['avatar' => $file])
        ->assertSessionHasErrors('avatar');
});

test('avatar upload rejects files over 2MB', function () {
    $file = UploadedFile::fake()->image('big.jpg')->size(3000);

    $this->actingAs($this->user)
        ->post('/profile/avatar', ['avatar' => $file])
        ->assertSessionHasErrors('avatar');
});
