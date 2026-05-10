<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(private RegistrationService $service) {}

    /**
     * Show the registration form.
     * Only accessible when no users exist (fresh install).
     */
    public function create(): Response|RedirectResponse
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
    public function store(RegisterRequest $request): RedirectResponse
    {
        // Use DB::transaction with a lock to prevent two simultaneous registration
        // requests both passing the User::exists() check and creating duplicate workspaces.
        $user = DB::transaction(function () use ($request) {
            // Re-check inside the transaction with a lock on the users table
            if (User::lockForUpdate()->exists()) {
                abort(403, 'Registration is closed. Contact your workspace admin to invite you.');
            }

            return $this->service->register($request->validated());
        });

        Auth::login($user);

        return redirect('/conversations')->with('success', 'Welcome to FusterAI! Your workspace is ready.');
    }
}
