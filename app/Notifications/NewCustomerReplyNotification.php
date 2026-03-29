<?php

namespace App\Notifications;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewCustomerReplyNotification extends Notification implements ShouldQueue, ShouldBroadcast
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
        return ['database', 'mail', 'broadcast'];
    }

    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $preview = mb_substr(strip_tags($this->thread->body), 0, 200);

        return (new MailMessage)
            ->subject("New reply on: {$this->conversation->subject}")
            ->greeting("Hi {$notifiable->name},")
            ->line("A customer replied to: **{$this->conversation->subject}**")
            ->line($preview)
            ->action('View Conversation', url("/conversations/{$this->conversation->id}"));
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'type'            => 'new_reply',
            'conversation_id' => $this->conversation->id,
            'subject'         => $this->conversation->subject,
            'preview'         => mb_substr(strip_tags($this->thread->body), 0, 200),
            'url'             => "/conversations/{$this->conversation->id}",
        ];
    }
}
