<?php

namespace Modules\SlaManager\Models;

use App\Domains\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SlaStatus extends Model
{
    protected $fillable = [
        'conversation_id',
        'sla_policy_id',
        'first_response_due_at',
        'resolution_due_at',
        'first_response_achieved_at',
        'resolved_at',
        'first_response_breached',
        'resolution_breached',
        'paused_at',
        'pause_offset_minutes',
    ];

    protected $casts = [
        'first_response_due_at'       => 'datetime',
        'resolution_due_at'           => 'datetime',
        'first_response_achieved_at'  => 'datetime',
        'resolved_at'                 => 'datetime',
        'paused_at'                   => 'datetime',
        'first_response_breached'     => 'boolean',
        'resolution_breached'         => 'boolean',
        'pause_offset_minutes'        => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }

    // ── Pause / Resume ────────────────────────────────────────────────────────

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    /**
     * Pause the SLA clock. No-op if already paused.
     */
    public function pause(): void
    {
        if ($this->isPaused()) {
            return;
        }
        $this->update(['paused_at' => now()]);
    }

    /**
     * Resume the SLA clock. Extends due dates by the paused duration and
     * accumulates the offset for future reference.
     */
    public function resume(): void
    {
        if (! $this->isPaused()) {
            return;
        }

        $pausedMinutes = max(0, (int) $this->paused_at->diffInMinutes(now(), false));

        $updates = [
            'paused_at'            => null,
            'pause_offset_minutes' => $this->pause_offset_minutes + $pausedMinutes,
        ];

        if ($this->first_response_achieved_at === null) {
            $updates['first_response_due_at'] = $this->first_response_due_at->addMinutes($pausedMinutes);
        }

        $updates['resolution_due_at'] = $this->resolution_due_at->addMinutes($pausedMinutes);

        $this->update($updates);
    }

    // ── Status Accessors ──────────────────────────────────────────────────────

    /**
     * 'achieved' | 'breached' | 'paused' | 'pending'
     */
    public function getFirstResponseStatusAttribute(): string
    {
        if ($this->first_response_achieved_at) {
            return 'achieved';
        }
        if ($this->isPaused()) {
            return 'paused';
        }
        if ($this->first_response_breached || ($this->first_response_due_at && now()->gt($this->first_response_due_at))) {
            return 'breached';
        }

        return 'pending';
    }

    /**
     * 'achieved' | 'breached' | 'paused' | 'pending'
     */
    public function getResolutionStatusAttribute(): string
    {
        if ($this->resolved_at) {
            return 'achieved';
        }
        if ($this->isPaused()) {
            return 'paused';
        }
        if ($this->resolution_breached || ($this->resolution_due_at && now()->gt($this->resolution_due_at))) {
            return 'breached';
        }

        return 'pending';
    }

    /**
     * Minutes remaining until first response breach (negative = already overdue).
     * Returns 0 while paused.
     */
    public function getFirstResponseRemainingMinutesAttribute(): int
    {
        if ($this->isPaused() || ! $this->first_response_due_at) {
            return 0;
        }

        return (int) now()->diffInMinutes($this->first_response_due_at, false);
    }

    /**
     * Minutes remaining until resolution breach (negative = already overdue).
     * Returns 0 while paused.
     */
    public function getResolutionRemainingMinutesAttribute(): int
    {
        if ($this->isPaused() || ! $this->resolution_due_at) {
            return 0;
        }

        return (int) now()->diffInMinutes($this->resolution_due_at, false);
    }
}
