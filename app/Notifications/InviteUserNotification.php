<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/invite/accept/' . $this->token . '?email=' . urlencode($notifiable->email));

        return (new MailMessage)
            ->subject('You\'ve been invited to ' . config('app.name'))
            ->greeting('Hello!')
            ->line('You\'ve been invited to join ' . config('app.name') . ' as a team member.')
            ->action('Accept Invitation', $url)
            ->line('This invite link will expire in ' . config('auth.passwords.users.expire') . ' minutes.')
            ->line('If you did not expect this invitation, you can ignore this email.');
    }
}
