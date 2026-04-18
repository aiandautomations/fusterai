<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    public function update(User $user, array $validated): void
    {
        $user->update($validated);
    }

    public function updateAvatar(User $user, UploadedFile $file): void
    {
        if ($user->avatar && str_contains($user->avatar, '/storage/users/avatars/')) {
            $oldPath = str_replace(url('/storage').'/', '', $user->avatar);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->store('users/avatars', 'public');
        $user->avatar = Storage::disk('public')->url($path);
        $user->save();
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => Hash::make($password)]);
    }
}
