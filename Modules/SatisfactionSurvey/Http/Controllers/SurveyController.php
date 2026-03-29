<?php

namespace Modules\SatisfactionSurvey\Http\Controllers;

use App\Domains\Conversation\Models\Conversation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Log;
use Modules\SatisfactionSurvey\Models\SurveyResponse;

class SurveyController extends Controller
{
    public function respond(Request $request): \Illuminate\View\View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This survey link has expired or is invalid.');
        }

        $request->validate([
            'conversation' => ['required', 'integer', 'exists:conversations,id'],
            'rating'       => ['required', 'in:good,bad'],
        ]);

        $conversation = Conversation::findOrFail($request->conversation);

        // Idempotent — if already responded, just show the thank you page
        $response = SurveyResponse::firstOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'customer_id'  => $conversation->customer_id,
                'rating'       => $request->rating,
                'ip_address'   => $request->ip(),
                'responded_at' => now(),
            ]
        );

        Log::info('Survey response recorded', [
            'conversation_id' => $conversation->id,
            'rating'          => $response->rating,
        ]);

        return view('satisfaction-survey::responded', [
            'rating'       => $response->rating,
            'mailboxName'  => $conversation->mailbox?->name ?? config('app.name'),
        ]);
    }
}
