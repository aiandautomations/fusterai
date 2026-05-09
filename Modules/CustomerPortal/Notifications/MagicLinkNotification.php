<?php

namespace Modules\CustomerPortal\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Workspace $workspace,
        private string $token,
    ) {}

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('portal.auth', [
            'workspace' => $this->workspace->slug,
            'token' => $this->token,
        ]);

        $portalName = $this->workspace->settings['portal']['name'] ?? $this->workspace->name.' Support';

        return (new MailMessage)
            ->subject("Your sign-in link for {$portalName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Click the button below to sign in to {$portalName}. This link expires in 60 minutes.")
            ->action('Sign in to Portal', $url)
            ->line('If you did not request this link, you can safely ignore this email.');
    }
}
