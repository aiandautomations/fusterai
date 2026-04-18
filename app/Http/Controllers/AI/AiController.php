<?php

namespace App\Http\Controllers\AI;

use App\Domains\AI\Jobs\GenerateReplySuggestionJob;
use App\Domains\AI\Jobs\SummarizeConversationJob;
use App\Domains\Conversation\Models\AiSuggestion;
use App\Domains\Conversation\Models\Conversation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function suggestReply(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        GenerateReplySuggestionJob::dispatch($conversation)->onQueue('ai');

        return response()->json(['status' => 'queued']);
    }

    public function summarize(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        SummarizeConversationJob::dispatch($conversation)->onQueue('ai');

        return response()->json(['status' => 'queued']);
    }

    public function acceptSuggestion(Request $request, AiSuggestion $suggestion): JsonResponse
    {
        $this->authorize('update', $suggestion->conversation);

        $suggestion->update(['accepted' => true]);

        return response()->json(['ok' => true]);
    }
}
