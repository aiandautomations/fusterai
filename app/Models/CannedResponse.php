<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CannedResponse extends Model
{
    protected $fillable = ['workspace_id', 'mailbox_id', 'name', 'content'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Mailbox\Models\Mailbox::class);
    }
}
