<?php

namespace App\Domains\Conversation\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }

    protected $fillable = ['workspace_id', 'name', 'color'];

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_tag');
    }
}
