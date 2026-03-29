<?php

namespace Modules\SatisfactionSurvey\Jobs;

use App\Domains\Conversation\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\SatisfactionSurvey\Mail\SurveyMail;
use Modules\SatisfactionSurvey\Models\SurveyResponse;

class SendSurveyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly Conversation $conversation) {}

    public function handle(): void
    {
        $customer = $this->conversation->customer;

        // Only send if conversation has a customer with an email address
        if (! $customer?->email) {
            return;
        }

        // Don't send if a response already exists
        if (SurveyResponse::where('conversation_id', $this->conversation->id)->exists()) {
            return;
        }

        $goodUrl = URL::temporarySignedRoute(
            'survey.respond',
            now()->addDays(7),
            ['conversation' => $this->conversation->id, 'rating' => 'good']
        );

        $badUrl = URL::temporarySignedRoute(
            'survey.respond',
            now()->addDays(7),
            ['conversation' => $this->conversation->id, 'rating' => 'bad']
        );

        Mail::to($customer->email, $customer->name)
            ->send(new SurveyMail($this->conversation, $goodUrl, $badUrl));
    }
}
