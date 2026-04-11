<?php

namespace App\Domains\Conversation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomView extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'color',
        'filters',
        'is_shared',
        'order',
    ];

    protected $casts = [
        'filters'   => 'array',
        'is_shared' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
