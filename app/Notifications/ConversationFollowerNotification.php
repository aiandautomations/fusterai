<?php

namespace App\Notifications;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversationFollowerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly Thread $thread,
    ) {
        $this->onQueue('notifications');
    }

    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $preview = mb_substr(strip_tags($this->thread->body), 0, 200);

        return (new MailMessage)
            ->subject("Update on followed conversation: {$this->conversation->subject}")
            ->greeting("Hi {$notifiable->name},")
            ->line("A new message was posted on: **{$this->conversation->subject}**")
            ->line($preview)
            ->action('View Conversation', url("/conversations/{$this->conversation->id}"));
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'follower_update',
            'conversation_id' => $this->conversation->id,
            'subject' => $this->conversation->subject,
            'preview' => mb_substr(strip_tags($this->thread->body), 0, 200),
            'url' => "/conversations/{$this->conversation->id}",
        ];
    }
}
