<?php

namespace App\Http\Controllers;

use App\Domains\Customer\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles one-click email unsubscribe links included in all outbound replies.
 *
 * The URL is signed (via URL::signedRoute) so it cannot be tampered with.
 * RFC 8058 compliant: supports both GET (confirmation page) and POST (one-click).
 */
class UnsubscribeController extends Controller
{
    public function show(Request $request, Customer $customer): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        return response()->view('unsubscribe', ['customer' => $customer]);
    }

    public function destroy(Request $request, Customer $customer): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $customer->update(['is_blocked' => true]);

        return response()->view('unsubscribe', [
            'customer' => $customer,
            'unsubscribed' => true,
        ]);
    }
}
