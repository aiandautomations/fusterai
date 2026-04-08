<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case Open    = 'open';
    case Pending = 'pending';
    case Closed  = 'closed';
    case Spam    = 'spam';

    public function label(): string
    {
        return match($this) {
            self::Open    => 'Open',
            self::Pending => 'Pending',
            self::Closed  => 'Closed',
            self::Spam    => 'Spam',
        };
    }
}
