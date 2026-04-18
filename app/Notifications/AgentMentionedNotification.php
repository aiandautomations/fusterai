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

class AgentMentionedNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly Thread $thread,
        public readonly string $mentionedBy,
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
            ->subject("You were mentioned in: {$this->conversation->subject}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->mentionedBy} mentioned you in a note on **{$this->conversation->subject}**")
            ->action('View Conversation', url("/conversations/{$this->conversation->id}"))
            ->line('Login to FusterAI to respond.');
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'mention',
            'conversation_id' => $this->conversation->id,
            'subject' => $this->conversation->subject,
            'mentioned_by' => $this->mentionedBy,
            'url' => "/conversations/{$this->conversation->id}",
        ];
    }
}
