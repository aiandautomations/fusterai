<?php

namespace App\Notifications;

use App\Domains\Conversation\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversationAssignedNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public readonly Conversation $conversation,
    ) {
        $this->onQueue('notifications');
    }

    public function via(mixed $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Conversation assigned to you')
            ->greeting("Hi {$notifiable->name},")
            ->line("A conversation has been assigned to you: **{$this->conversation->subject}**")
            ->action('View Conversation', url("/conversations/{$this->conversation->id}"))
            ->line('Login to FusterAI to respond.');
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'type'            => 'assigned',
            'conversation_id' => $this->conversation->id,
            'subject'         => $this->conversation->subject,
            'url'             => "/conversations/{$this->conversation->id}",
        ];
    }
}
