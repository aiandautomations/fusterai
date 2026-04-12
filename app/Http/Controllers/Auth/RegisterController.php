<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(private RegistrationService $service) {}

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
    public function store(RegisterRequest $request): \Illuminate\Http\RedirectResponse
    {
        if (User::exists()) {
            abort(403, 'Registration is closed. Contact your workspace admin to invite you.');
        }

        $user = $this->service->register($request->validated());

        Auth::login($user);

        return redirect('/conversations')->with('success', 'Welcome to FusterAI! Your workspace is ready.');
    }
}
