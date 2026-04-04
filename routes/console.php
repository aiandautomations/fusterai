<?php

use Illuminate\Support\Facades\Schedule;

// Fetch emails every minute
Schedule::command('emails:fetch')->everyMinute()->withoutOverlapping();

// Wake snoozed conversations back to open
Schedule::call(function () {
    \App\Domains\Conversation\Models\Conversation::query()
        ->where('status', 'open')
        ->where('snoozed_until', '<=', now())
        ->whereNotNull('snoozed_until')
        ->update(['snoozed_until' => null]);
})->everyMinute();

// Horizon snapshot (metrics)
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Daily digest email at 8am
Schedule::command('notifications:digest')->dailyAt('08:00');

// Auto-close stale pending conversations (runs daily at midnight)
Schedule::call(function () {
    \App\Domains\Mailbox\Models\Mailbox::whereNotNull('auto_reply_config')
        ->get()
        ->each(function (\App\Domains\Mailbox\Models\Mailbox $mailbox) {
            $days = (int) ($mailbox->auto_reply_config['auto_close_pending_days'] ?? 0);
            if ($days <= 0) {
                return;
            }

            \App\Domains\Conversation\Models\Conversation::where('mailbox_id', $mailbox->id)
                ->where('status', 'pending')
                ->where('last_reply_at', '<=', now()->subDays($days))
                ->each(function (\App\Domains\Conversation\Models\Conversation $conversation) {
                    $conversation->update(['status' => 'closed']);

                    $conversation->threads()->create([
                        'type'       => 'activity',
                        'body'       => '<p>Conversation automatically closed after no customer reply.</p>',
                        'body_plain' => 'Conversation automatically closed after no customer reply.',
                        'source'     => 'web',
                    ]);

                    broadcast(new \App\Events\ConversationUpdated($conversation->fresh()));
                });
        });
})->daily();
