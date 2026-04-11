<?php

namespace App\Http\Controllers;

use App\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $service) {}

    public function show(Request $request)
    {
        return Inertia::render('Profile/Index', [
            'user' => $request->user()->only('id', 'name', 'email', 'avatar', 'role', 'signature'),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'signature' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->service->update($user, $validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $this->service->updateAvatar($request->user(), $request->file('avatar'));

        return back()->with('success', 'Avatar updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', Password::defaults(), 'confirmed'],
        ]);

        $this->service->updatePassword($request->user(), $request->password);

        return back()->with('success', 'Password updated successfully.');
    }
}
