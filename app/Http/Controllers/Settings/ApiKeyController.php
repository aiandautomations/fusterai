<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiKeyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = $request->user()->tokens()
            ->where('revoked', false)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'created_at', 'revoked']);

        return Inertia::render('Settings/ApiKeys', [
            'tokens' => $tokens,
        ]);
    }

    public function store(StoreApiKeyRequest $request): RedirectResponse
    {
        $result = $request->user()->createToken($request->name);

        return redirect()->route('settings.api-keys')
            ->with('token', $result->accessToken)
            ->with('success', 'Token created successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $token = $request->user()->tokens()->findOrFail($id);
        $token->revoke();

        return redirect()->route('settings.api-keys')->with('success', 'Token revoked.');
    }
}
