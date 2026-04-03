<?php

namespace Modules\SlaManager\Notifications;

use App\Domains\Conversation\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachedNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    /**
     * @param  string  $breachType  'first_response' | 'resolution'
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $breachType,
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
        $label = $this->breachType === 'first_response' ? 'First Response' : 'Resolution';

        return (new MailMessage)
            ->subject("SLA {$label} Breached — {$this->conversation->subject}")
            ->greeting("Hi {$notifiable->name},")
            ->line("The **{$label}** SLA target has been breached for conversation: **{$this->conversation->subject}**")
            ->action('View Conversation', url("/conversations/{$this->conversation->id}"))
            ->line('Please respond as soon as possible.');
    }

    public function toArray(mixed $notifiable): array
    {
        $label = $this->breachType === 'first_response' ? 'First Response' : 'Resolution';

        return [
            'type'            => 'sla_breached',
            'breach_type'     => $this->breachType,
            'conversation_id' => $this->conversation->id,
            'subject'         => $this->conversation->subject,
            'message'         => "SLA {$label} breached for: {$this->conversation->subject}",
            'url'             => "/conversations/{$this->conversation->id}",
        ];
    }
}
