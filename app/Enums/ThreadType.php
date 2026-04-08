<?php

namespace App\Enums;

enum ThreadType: string
{
    case Message      = 'message';
    case Note         = 'note';
    case Activity     = 'activity';
    case AiSuggestion = 'ai_suggestion';

    public function label(): string
    {
        return match($this) {
            self::Message      => 'Message',
            self::Note         => 'Note',
            self::Activity     => 'Activity',
            self::AiSuggestion => 'AI Suggestion',
        };
    }
}
