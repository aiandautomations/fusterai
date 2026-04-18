<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInviteRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the "accept invite" flow for users who were created by an admin.
 * Reuses Laravel's password broker for secure token generation + expiry.
 */
class InviteController extends Controller
{
    /**
     * Show the accept-invite / set-password page.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $token = $request->route('token');
        $email = $request->query('email');

        if (! $token || ! $email) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/AcceptInvite', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Complete account setup: validate token, set name + password, log in.
     */
    public function store(AcceptInviteRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password'],
                'token' => $validated['token'],
            ],
            function (User $user, string $password) use ($validated): void {
                $user->forceFill([
                    'name' => $validated['name'],
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            return back()->withErrors(['email' => __($status)]);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        Auth::login($user);

        return redirect('/conversations')->with('success', 'Welcome to FusterAI!');
    }
}
