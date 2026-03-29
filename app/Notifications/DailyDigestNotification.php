<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly array $stats,
    ) {
        $this->onQueue('notifications');
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your daily {$this->stats['workspace_name']} digest")
            ->greeting("Good morning, {$notifiable->name}!")
            ->line("Here's your helpdesk summary for today:")
            ->line("📬 **Open conversations:** {$this->stats['open']}")
            ->line("⏳ **Pending:** {$this->stats['pending']}")
            ->line("👤 **Assigned to you:** {$this->stats['assigned_to_me']}")
            ->action('Open FusterAI', url('/conversations'));
    }
}
