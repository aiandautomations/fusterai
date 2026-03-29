<?php

namespace App\Domains\Conversation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Folder extends Model
{
    protected $fillable = ['workspace_id', 'created_by_user_id', 'name', 'color', 'icon', 'order'];

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_folder');
    }
}
