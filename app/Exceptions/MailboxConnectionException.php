<?php

namespace App\Exceptions;

use RuntimeException;

class MailboxConnectionException extends RuntimeException
{
    public function __construct(string $mailboxName, string $reason = '')
    {
        parent::__construct(
            "Failed to connect to mailbox \"{$mailboxName}\"" . ($reason ? ": {$reason}" : '.')
        );
    }
}
