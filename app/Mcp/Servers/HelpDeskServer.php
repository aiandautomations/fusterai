<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateNote;
use App\Mcp\Tools\GetConversation;
use App\Mcp\Tools\GetCustomerHistory;
use App\Mcp\Tools\SearchConversations;
use App\Mcp\Tools\UpdateConversation;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('FusterAI HelpDesk')]
#[Version('1.0.0')]
#[Instructions('Tools for querying and managing HelpDesk conversations, customers, and notes.')]
class HelpDeskServer extends Server
{
    protected function boot(): void
    {
        $user        = auth()->user();
        $workspaceId = $user !== null ? ($user->workspace_id ?? 0) : 0;
        $userId      = $user !== null ? ($user->id ?? 0) : 0;

        $this->tools = [
            new GetConversation(),
            new SearchConversations($workspaceId),
            new GetCustomerHistory(),
            new CreateNote($userId),
            new UpdateConversation($workspaceId),
        ];
    }
}
