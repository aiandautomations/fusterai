<?php

namespace App\Enums;

enum ChannelType: string
{
    case Email = 'email';
    case Chat = 'chat';
    case WhatsApp = 'whatsapp';
    case Slack = 'slack';
    case Api = 'api';
    case Sms = 'sms';
    case Portal = 'portal';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Chat => 'Live Chat',
            self::WhatsApp => 'WhatsApp',
            self::Slack => 'Slack',
            self::Api => 'API',
            self::Sms => 'SMS',
            self::Portal => 'Customer Portal',
        };
    }
}
