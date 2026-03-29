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
