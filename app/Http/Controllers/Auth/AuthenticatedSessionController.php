<?php

namespace App\Http\Controllers\Auth;

use App\Events\AgentStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'canRegister' => ! User::exists(),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = $request->user();
        $user->update([
            'status' => 'online',
            'last_active_at' => $user->last_active_at ?? now(),
        ]);
        broadcast(new AgentStatusChanged($user->workspace_id, $user->id, 'online'))->toOthers();

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $user->update(['status' => 'offline']);
            broadcast(new AgentStatusChanged($user->workspace_id, $user->id, 'offline'))->toOthers();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
