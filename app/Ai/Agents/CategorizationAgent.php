<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-4-5-20251001')]
#[MaxTokens(256)]
#[Temperature(0.1)]
class CategorizationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You categorize customer support tickets.
        Given a subject and message, determine:
        1. Priority: low, normal, high, or urgent
        2. Tags: 1-3 relevant tags from: billing, bug, feature-request, account, shipping, refund, technical, general
        3. A one-line summary (max 80 chars)

        Rules for priority:
        - urgent: service down, data loss, security issue, payment failure
        - high: key feature broken, significant inconvenience
        - normal: general questions, minor issues
        - low: feature requests, feedback, non-urgent enquiries
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'priority' => $schema->string()
                ->enum(['low', 'normal', 'high', 'urgent'])
                ->description('Ticket priority')
                ->required(),

            'tags' => $schema->array()
                ->items($schema->string()->enum(['billing', 'bug', 'feature-request', 'account', 'shipping', 'refund', 'technical', 'general']))
                ->description('1-3 relevant tags')
                ->required(),

            'summary' => $schema->string()
                ->description('One-line summary under 80 characters')
                ->required(),
        ];
    }
}
