<?php

namespace Modules\SlaManager\Models;

use App\Domains\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'first_response_due_at'       => 'datetime',
        'resolution_due_at'           => 'datetime',
        'first_response_achieved_at'  => 'datetime',
        'resolved_at'                 => 'datetime',
        'first_response_breached'     => 'boolean',
        'resolution_breached'         => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }

    /**
     * Whether the first response target has been met or is still pending.
     */
    public function getFirstResponseStatusAttribute(): string
    {
        if ($this->first_response_achieved_at) {
            return 'achieved';
        }
        if ($this->first_response_breached || now()->gt($this->first_response_due_at)) {
            return 'breached';
        }

        return 'pending';
    }

    /**
     * Whether the resolution target has been met or is still pending.
     */
    public function getResolutionStatusAttribute(): string
    {
        if ($this->resolved_at) {
            return 'achieved';
        }
        if ($this->resolution_breached || now()->gt($this->resolution_due_at)) {
            return 'breached';
        }

        return 'pending';
    }

    /**
     * Minutes remaining until first response breach (negative = already breached).
     */
    public function getFirstResponseRemainingMinutesAttribute(): int
    {
        return (int) now()->diffInMinutes($this->first_response_due_at, false);
    }

    /**
     * Minutes remaining until resolution breach (negative = already breached).
     */
    public function getResolutionRemainingMinutesAttribute(): int
    {
        return (int) now()->diffInMinutes($this->resolution_due_at, false);
    }
}
