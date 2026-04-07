<?php

namespace App\Mcp\Tools;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Tag;
use App\Events\ConversationUpdated;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
class UpdateConversation extends Tool
{
    protected string $name        = 'update_conversation';
    protected string $description = "Update a conversation's status, priority, assignee, or tags.";

    public function __construct(private readonly int $workspaceId) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'conversation_id' => $schema->integer()->description('The conversation ID')->required(),
            'status'          => $schema->string()->enum(['open', 'pending', 'closed'])->description('New status'),
            'priority'        => $schema->string()->enum(['low', 'normal', 'high', 'urgent'])->description('New priority'),
            'assign_to_email' => $schema->string()->description('Email of agent to assign'),
            'tags'            => $schema->array()->items($schema->string())->description('Replace all tags with these names'),
        ];
    }

    public function handle(Request $request): Response
    {
        $id           = (int) $request->get('conversation_id');
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return Response::error("Conversation #{$id} not found.");
        }

        // Scoped authorization: only allow updates within same workspace
        if ($conversation->workspace_id !== $this->workspaceId) {
            return Response::error('Access denied.');
        }

        $updates = array_filter([
            'status'   => $request->get('status'),
            'priority' => $request->get('priority'),
        ]);

        if ($email = $request->string('assign_to_email')) {
            $agent = User::where('email', $email)->where('workspace_id', $this->workspaceId)->first();
            if ($agent) $updates['assigned_user_id'] = $agent->id;
        }

        if (!empty($updates)) {
            $conversation->update($updates);
        }

        if ($request->has('tags')) {
            $tagIds = collect($request->get('tags', []))->map(fn ($name) => Tag::firstOrCreate(
                ['workspace_id' => $this->workspaceId, 'name' => strtolower(trim($name))],
                ['color' => '#6b7280'],
            )->id)->all();
            $conversation->tags()->sync($tagIds);
        }

        broadcast(new ConversationUpdated($conversation));

        return Response::json(['success' => true, 'conversation_id' => $id]);
    }
}
