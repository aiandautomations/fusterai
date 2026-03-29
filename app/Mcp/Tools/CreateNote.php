<?php

namespace App\Mcp\Tools;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Events\ConversationUpdated;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
class CreateNote extends Tool
{
    protected string $name        = 'create_note';
    protected string $description = 'Add an internal note to a conversation. Notes are only visible to agents, not customers.';

    public function __construct(private readonly int $userId) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'conversation_id' => $schema->integer()->description('The conversation ID')->required(),
            'note'            => $schema->string()->description('Note content (plain text)')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $id           = (int) $request->get('conversation_id');
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return Response::error("Conversation #{$id} not found.");
        }

        $thread = Thread::create([
            'conversation_id' => $id,
            'user_id'         => $this->userId,
            'type'            => 'note',
            'body'            => nl2br(e($request->string('note'))),
            'status'          => 'note',
        ]);

        broadcast(new ConversationUpdated($conversation));

        return Response::json(['success' => true, 'thread_id' => $thread->id]);
    }
}
