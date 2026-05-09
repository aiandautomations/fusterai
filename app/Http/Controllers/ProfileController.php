<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $service) {}

    public function show(Request $request)
    {
        return Inertia::render('Profile/Index', [
            'user' => $request->user()->only(['id', 'name', 'email', 'avatar', 'role', 'signature']),
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $this->service->update($request->user(), $request->validated());

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

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $this->service->updatePassword($request->user(), $request->password);

        return back()->with('success', 'Password updated successfully.');
    }
}
