<?php

use App\Domains\Conversation\Jobs\SendAutoReplyJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use App\Services\OfficeHoursService;
use Carbon\Carbon;

// ── OfficeHoursService unit tests ─────────────────────────────────────────────

test('isOpen returns true when current time is within schedule', function () {
    $service = new OfficeHoursService;
    $now = Carbon::parse('2025-01-06 10:00:00', 'UTC'); // Monday

    $schedule = ['1' => ['open' => '09:00', 'close' => '17:00']];

    expect($service->isOpen($schedule, 'UTC', $now))->toBeTrue();
});

test('isOpen returns false when current time is before open', function () {
    $service = new OfficeHoursService;
    $now = Carbon::parse('2025-01-06 07:00:00', 'UTC'); // Monday 7am

    $schedule = ['1' => ['open' => '09:00', 'close' => '17:00']];

    expect($service->isOpen($schedule, 'UTC', $now))->toBeFalse();
});

test('isOpen returns false when current time is after close', function () {
    $service = new OfficeHoursService;
    $now = Carbon::parse('2025-01-06 18:00:00', 'UTC'); // Monday 6pm

    $schedule = ['1' => ['open' => '09:00', 'close' => '17:00']];

    expect($service->isOpen($schedule, 'UTC', $now))->toBeFalse();
});

test('isOpen returns false for a closed day (null schedule)', function () {
    $service = new OfficeHoursService;
    $now = Carbon::parse('2025-01-05 10:00:00', 'UTC'); // Sunday

    $schedule = ['1' => ['open' => '09:00', 'close' => '17:00']]; // only Monday

    expect($service->isOpen($schedule, 'UTC', $now))->toBeFalse();
});

test('isOpen respects timezone', function () {
    $service = new OfficeHoursService;
    // 10:00 UTC = 05:00 America/New_York (before open)
    $now = Carbon::parse('2025-01-06 10:00:00', 'UTC'); // Monday

    $schedule = ['1' => ['open' => '09:00', 'close' => '17:00']];

    expect($service->isOpen($schedule, 'America/New_York', $now))->toBeFalse();
});

// ── SendAutoReplyJob integration tests ───────────────────────────────────────

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->customer  = Customer::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'customer@example.com',
    ]);
});

test('out-of-hours auto-reply logs an activity thread', function () {
    $mailbox = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'auto_reply_config' => [
            'enabled' => false,
            'office_hours' => [
                'enabled' => true,
                'timezone' => 'UTC',
                'schedule' => [], // all days closed → always outside hours
                'message' => 'We are out of office.',
            ],
        ],
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    app(SendAutoReplyJob::class, ['conversation' => $conversation])->handle();

    expect($conversation->threads()->where('type', 'activity')->where('body', 'Auto-reply sent to customer.')->exists())->toBeTrue();
});

test('within-hours auto-reply logs an activity thread', function () {
    $schedule = array_fill_keys(['0','1','2','3','4','5','6'], ['open' => '00:00', 'close' => '23:59']);

    $mailbox = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'auto_reply_config' => [
            'enabled' => true,
            'body' => 'Thanks for contacting us!',
            'office_hours' => [
                'enabled' => true,
                'timezone' => 'UTC',
                'schedule' => $schedule,
            ],
        ],
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    app(SendAutoReplyJob::class, ['conversation' => $conversation])->handle();

    expect($conversation->threads()->where('type', 'activity')->where('body', 'Auto-reply sent to customer.')->exists())->toBeTrue();
});

test('no activity thread created when auto-reply and office hours are both disabled', function () {
    $mailbox = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'auto_reply_config' => ['enabled' => false],
    ]);

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    app(SendAutoReplyJob::class, ['conversation' => $conversation])->handle();

    expect($conversation->threads()->where('type', 'activity')->exists())->toBeFalse();
});
