<?php

namespace App\Ai\Agents;

use App\Ai\Tools\SearchKnowledgeBase;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Support\Hooks;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-opus-4-6')]
#[MaxTokens(1024)]
#[Temperature(0.7)]
class ReplySuggestionAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        private readonly Conversation $conversation,
    ) {}

    public function instructions(): string
    {
        $customer = $this->conversation->customer;
        $mailbox = $this->conversation->mailbox;

        $base = <<<INSTRUCTIONS
        You are a customer support assistant helping agents at {$mailbox?->name}.
        You suggest professional, empathetic, and concise email replies.

        Customer name: {$customer?->name}
        Customer email: {$customer?->email}

        Rules:
        - Address the customer by their first name
        - Be warm but professional
        - Reference specific details from the conversation
        - Use the knowledge base tool if the question may be answered by documentation
        - Keep replies focused and under 200 words unless the issue demands more detail
        - Do NOT add "Subject:" lines — only the reply body
        - End with a helpful closing and the agent's name if available
        INSTRUCTIONS;

        return Hooks::applyFilters('ai.system_prompt', $base, $this->conversation);
    }

    /**
     * Provide conversation history as context messages.
     */
    public function messages(): iterable
    {
        $messages = [];

        foreach ($this->conversation->threads->where('type', 'message')->take(10) as $thread) {
            /** @var Thread $thread */
            $role = $thread->isFromCustomer() ? 'user' : 'assistant';
            $content = strip_tags((string) $thread->body);
            $messages[] = new Message($role, $content);
        }

        return $messages;
    }

    public function tools(): iterable
    {
        return [
            new SearchKnowledgeBase($this->conversation->workspace_id),
        ];
    }
}
