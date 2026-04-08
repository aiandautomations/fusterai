<?php

namespace App\Domains\Conversation\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property bool $is_unread
 * @property-read \App\Domains\Mailbox\Models\Mailbox|null $mailbox
 * @property-read \App\Domains\Customer\Models\Customer|null $customer
 * @property-read \App\Models\User|null $assignedUser
 * @property-read \App\Models\User|null $assignee
 */
class Conversation extends Model
{
    /** Return a human-readable label for a raw status string. */
    public static function statusLabel(string $status): string
    {
        return ConversationStatus::tryFrom($status)?->label() ?? ucfirst($status);
    }

    /** @use HasFactory<ConversationFactory> */
    use HasFactory;
    use Searchable;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'priority', 'assigned_user_id', 'subject', 'snoozed_until'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('conversation');
    }

    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }

    protected $fillable = [
        'workspace_id',
        'mailbox_id',
        'customer_id',
        'assigned_user_id',
        'status',
        'priority',
        'subject',
        'channel_type',
        'channel_id',
        'ai_summary',
        'ai_tags',
        'last_reply_at',
        'snoozed_until',
    ];

    protected $casts = [
        'status'        => ConversationStatus::class,
        'priority'      => ConversationPriority::class,
        'ai_tags'       => 'array',
        'last_reply_at' => 'datetime',
        'snoozed_until' => 'datetime',
    ];

    // ── Scout ────────────────────────────────────────────────────

    public function toSearchableArray(): array
    {
        return [
            'id'           => $this->id,
            'workspace_id' => $this->workspace_id,
            'subject'      => $this->subject,
            'status'       => $this->status,
            'priority'     => $this->priority,
            'customer'     => $this->customer?->name,
            'customer_email' => $this->customer?->email,
            'last_reply_at' => $this->last_reply_at?->timestamp,
        ];
    }

    public function searchableAs(): string
    {
        return 'conversations';
    }

    // ── Relations ────────────────────────────────────────────────

    /** @return BelongsTo<Mailbox, $this> */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_user_id');
    }

    /** Alias for assignedUser — used by MCP tools and search. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_user_id');
    }

    /** @return HasMany<Thread, $this> */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class)->orderBy('created_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'conversation_tag');
    }

    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(Folder::class, 'conversation_folder');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'followers');
    }

    public function aiSuggestions(): HasMany
    {
        return $this->hasMany(AiSuggestion::class);
    }

    public function reads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ConversationRead::class);
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_user_id');
    }

    public function scopeForMailbox(Builder $query, int $mailboxId): Builder
    {
        return $query->where('mailbox_id', $mailboxId);
    }

    public function scopeSnoozed(Builder $query): Builder
    {
        return $query->whereNotNull('snoozed_until')->where('snoozed_until', '>', now());
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isSnoozed(): bool
    {
        return $this->snoozed_until && $this->snoozed_until->isFuture();
    }

    public function latestThread(): ?Thread
    {
        /** @var Thread|null */
        return $this->threads()->latest()->first();
    }

    public function lastCustomerThread(): ?Thread
    {
        /** @var Thread|null */
        return $this->threads()
            ->where('type', 'message')
            ->whereNotNull('customer_id')
            ->latest()
            ->first();
    }
}
