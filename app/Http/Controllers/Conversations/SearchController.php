<?php

namespace App\Http\Controllers\Conversations;

use App\Domains\Conversation\Models\Conversation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = Conversation::search($query)
            ->where('workspace_id', $request->user()->workspace_id)
            ->take(10)
            ->get()
            ->load(['customer', 'mailbox']);

        return response()->json([
            'results' => $results->map(fn($c) => [
                'id'       => $c->id,
                'subject'  => $c->subject,
                'status'   => $c->status,
                'customer' => $c->customer?->name,
                'mailbox'  => $c->mailbox?->name,
                'url'      => "/conversations/{$c->id}",
            ]),
        ]);
    }
}
