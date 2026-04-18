<?php

namespace Modules\SatisfactionSurvey\Mail;

use App\Domains\Conversation\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SurveyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $goodUrl,
        public readonly string $badUrl,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('How did we do? — '.$this->conversation->subject)
            ->view('satisfaction-survey::emails.survey');
    }
}
