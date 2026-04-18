<?php

namespace App\Mcp\Tools;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetConversation extends Tool
{
    protected string $name = 'get_conversation';

    protected string $description = 'Retrieve a conversation by ID, including threads, customer info, tags, and current status.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'conversation_id' => $schema->integer()
                ->description('The conversation ID')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $id = (int) $request->get('conversation_id');
        $conversation = Conversation::with(['customer', 'threads', 'tags', 'assignee'])->find($id);

        if (! $conversation) {
            return Response::error("Conversation #{$id} not found.");
        }

        $threads = $conversation->threads
            ->where('type', 'message')
            ->map(function ($t) use ($conversation) {
                /** @var Thread $t */
                return [
                    'from' => $t->isFromCustomer() ? ($conversation->customer !== null ? $conversation->customer->name ?? 'Customer' : 'Customer') : (($t->user !== null ? $t->user->name : null) ?? 'Agent'),
                    'body' => mb_substr(strip_tags((string) $t->body), 0, 500),
                    'created' => $t->created_at->toDateTimeString(),
                ];
            });

        return Response::json([
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
            'customer' => [
                'name' => $conversation->customer?->name,
                'email' => $conversation->customer?->email,
            ],
            'assignee' => $conversation->assignee?->name,
            'tags' => $conversation->tags->pluck('name'),
            'ai_summary' => $conversation->ai_summary,
            'threads' => $threads,
        ]);
    }
}
