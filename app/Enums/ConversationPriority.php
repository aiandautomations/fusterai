<?php

namespace App\Enums;

enum ConversationPriority: string
{
    case Low    = 'low';
    case Normal = 'normal';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::Low    => 'Low',
            self::Normal => 'Normal',
            self::High   => 'High',
            self::Urgent => 'Urgent',
        };
    }
}
