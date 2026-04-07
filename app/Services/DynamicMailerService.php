<?php

namespace App\Services;

use Illuminate\Mail\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Builds an Illuminate Mailer from a per-mailbox SMTP config array.
 * Extracted to avoid duplicating the same SMTP construction in every outbound job.
 */
class DynamicMailerService
{
    public function fromSmtpConfig(array $smtp): Mailer
    {
        $transport = new EsmtpTransport(
            host: $smtp['host'] ?? 'localhost',
            port: (int) ($smtp['port'] ?? 587),
            tls:  ($smtp['encryption'] ?? 'tls') === 'tls',
        );

        if (!empty($smtp['username'])) {
            $transport->setUsername($smtp['username']);
            $transport->setPassword($smtp['password'] ?? '');
        }

        return new Mailer('dynamic', app('view'), $transport, app('events'));
    }
}
