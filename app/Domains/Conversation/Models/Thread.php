<?php

namespace App\Domains\Conversation\Models;

use App\Domains\Customer\Models\Customer;
use App\Enums\ThreadType;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Thread extends Model
{
    /** @use HasFactory<ThreadFactory> */
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'body', 'source'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('thread');
    }

    protected static function newFactory(): ThreadFactory
    {
        return ThreadFactory::new();
    }
    protected $fillable = [
        'conversation_id',
        'user_id',
        'customer_id',
        'type',
        'body',
        'body_plain',
        'source',
        'meta',
    ];

    protected $casts = [
        'type' => ThreadType::class,
        'meta' => 'array',
    ];

    // ── Relations ────────────────────────────────────────────────

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function isFromCustomer(): bool
    {
        return $this->customer_id !== null;
    }

    public function isNote(): bool
    {
        return $this->type === ThreadType::Note;
    }

    public function isMessage(): bool
    {
        return $this->type === ThreadType::Message;
    }

    public function author(): \App\Models\User|Customer|null
    {
        /** @var \App\Models\User|Customer|null */
        return $this->user ?? $this->customer;
    }

    public function authorName(): string
    {
        $author = $this->author();
        return $author !== null ? ($author->name ?? 'Unknown') : 'Unknown';
    }
}
