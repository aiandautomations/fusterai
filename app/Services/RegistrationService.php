<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegistrationService
{
    /**
     * Create the initial workspace and super-admin user.
     * Only used during fresh-install registration.
     */
    public function register(array $validated): User
    {
        $workspace = Workspace::create([
            'name' => $validated['workspace_name'],
            'slug' => Str::slug($validated['workspace_name']),
        ]);

        return User::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'super_admin',
        ]);
    }
}
