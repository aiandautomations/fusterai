<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     * Only accessible when no users exist (fresh install).
     */
    public function create(): Response|\Illuminate\Http\RedirectResponse
    {
        if (User::exists()) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/Register');
    }

    /**
     * Handle workspace + first admin registration.
     * Only allowed when no users exist in the database.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        if (User::exists()) {
            abort(403, 'Registration is closed. Contact your workspace admin to invite you.');
        }

        $validated = $request->validate([
            'workspace_name' => ['required', 'string', 'max:100'],
            'name'           => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email', 'max:255', 'unique:users'],
            'password'       => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $workspace = Workspace::create([
            'name' => $validated['workspace_name'],
            'slug' => Str::slug($validated['workspace_name']),
        ]);

        $user = User::create([
            'workspace_id' => $workspace->id,
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'password'     => Hash::make($validated['password']),
            'role'         => 'super_admin',
        ]);

        Auth::login($user);

        return redirect('/conversations')->with('success', 'Welcome to FusterAI! Your workspace is ready.');
    }
}
