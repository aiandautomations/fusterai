<?php

namespace App\Mcp\Tools;

use App\Domains\Conversation\Models\Conversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchConversations extends Tool
{
    protected string $name        = 'search_conversations';
    protected string $description = 'Search conversations by subject or customer. Returns matching conversations.';

    public function __construct(private readonly int $workspaceId) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'  => $schema->string()->description('Search term')->required(),
            'status' => $schema->string()->enum(['open', 'pending', 'closed', 'snoozed'])->description('Filter by status'),
            'limit'  => $schema->integer()->description('Max results (1–25, default 10)')->min(1)->max(25),
        ];
    }

    public function handle(Request $request): Response
    {
        $query  = $request->string('query');
        $status = $request->string('status');
        $limit  = min((int) ($request->get('limit', 10)), 25);

        $builder = Conversation::with(['customer', 'assignee'])
            ->where('workspace_id', $this->workspaceId)
            ->where(fn ($q) => $q
                ->where('subject', 'ilike', "%{$query}%")
                ->orWhereHas('customer', fn ($cq) => $cq
                    ->where('name', 'ilike', "%{$query}%")
                    ->orWhere('email', 'ilike', "%{$query}%")
                )
            );

        if ($status) {
            $builder->where('status', $status);
        }

        $results = $builder->latest()->limit($limit)->get()->map(fn ($c) => [
            'id'       => $c->id,
            'subject'  => $c->subject,
            'status'   => $c->status,
            'priority' => $c->priority,
            'customer' => "{$c->customer?->name} <{$c->customer?->email}>",
            'assignee' => $c->assignee?->name,
            'updated'  => $c->updated_at->toDateTimeString(),
        ]);

        return Response::json(['count' => $results->count(), 'results' => $results]);
    }
}
