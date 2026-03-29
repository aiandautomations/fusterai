<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-4-5-20251001')]
#[MaxTokens(512)]
#[Temperature(0.3)]
class SummarizationAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You summarize customer support conversations into 2-3 concise bullet points.
        Format as plain text bullets starting with "•".
        Cover: the customer's main issue, key actions taken, and current resolution status.
        Be factual and neutral — no commentary.
        INSTRUCTIONS;
    }
}
