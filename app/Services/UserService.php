<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Create a user with a random placeholder password and dispatch an invite email
     * so the new user can set their own password via the reset flow.
     */
    public function invite(array $validated, int $workspaceId): User
    {
        $user = User::create([
            'workspace_id' => $workspaceId,
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'role'         => $validated['role'],
            'password'     => Hash::make(Str::random(32)),
        ]);

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }

    public function update(User $user, array $validated): void
    {
        $user->update($validated);
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function syncMailboxes(User $user, array $mailboxIds): void
    {
        $user->mailboxes()->sync($mailboxIds);
    }
}
