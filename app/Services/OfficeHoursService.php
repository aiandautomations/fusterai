<?php

namespace App\Services;

use Carbon\Carbon;

class OfficeHoursService
{
    /**
     * Check whether $now falls within the configured office hours schedule.
     *
     * @param  array<string, array{open: string, close: string}|null>  $schedule  Keyed 0–6 (0 = Sunday)
     */
    public function isOpen(array $schedule, string $timezone, ?Carbon $now = null): bool
    {
        $now = ($now ?? Carbon::now())->copy()->setTimezone($timezone);

        $dayOfWeek = (string) $now->dayOfWeek; // 0 = Sunday … 6 = Saturday
        $hours = $schedule[$dayOfWeek] ?? null;

        if (! $hours || empty($hours['open']) || empty($hours['close'])) {
            return false;
        }

        $open  = Carbon::createFromFormat('H:i', $hours['open'],  $timezone)->setDateFrom($now);
        $close = Carbon::createFromFormat('H:i', $hours['close'], $timezone)->setDateFrom($now);

        return $now->between($open, $close);
    }
}
