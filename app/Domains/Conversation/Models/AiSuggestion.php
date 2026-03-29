<?php

namespace App\Domains\Conversation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSuggestion extends Model
{
    protected $fillable = [
        'conversation_id',
        'thread_id',
        'type',
        'content',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'accepted',
    ];

    protected $casts = [
        'accepted' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function totalTokens(): int
    {
        return $this->prompt_tokens + $this->completion_tokens;
    }
}
