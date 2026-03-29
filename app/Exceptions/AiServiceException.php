<?php

namespace App\Exceptions;

use RuntimeException;

class AiServiceException extends RuntimeException
{
    public function __construct(string $operation = 'AI request', string $reason = '')
    {
        parent::__construct(
            "The {$operation} failed" . ($reason ? ": {$reason}" : '. Please try again.')
        );
    }
}
